<?php

namespace App\Application\Exports;

use App\Domain\Exports\IcsCalendar;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Recurrence\RecurrenceOccurrenceGenerator;
use App\Domain\Scheduling\Recurrence\RecurrenceRule;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentStatus;
use App\Domain\Tasks\Contracts\TaskRepository;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Exports the owner's selected schedule range as a valid iCalendar (.ics)
 * document (FR-30 / TASK-143):
 *
 * - schedule assignments (non-cancelled) become VEVENTs with the task title as
 *   SUMMARY;
 * - one-time / permanent Hard Landscape events become single VEVENTs;
 * - recurring Hard Landscape events are expanded within the selected window via
 *   the deterministic recurrence generator, so the export only contains events
 *   that actually fall in the requested range.
 *
 * Internal database identifiers are never exposed: VEVENT UIDs are derived from
 * a content hash and only exportable fields are written (NFR-03).
 */
final readonly class ExportScheduleIcsUseCase
{
    public function __construct(
        private ScheduleAssignmentRepository $assignments,
        private HardLandscapeRepository $hardLandscape,
        private TaskRepository $tasks,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $from, CarbonImmutable $to): string
    {
        if (! $to->greaterThanOrEqualTo($from)) {
            throw new InvalidArgumentException('Export "to" date must be on or after "from".');
        }

        $windowStart = $from->startOfDay();
        $windowEnd = $to->endOfDay();

        $calendar = new IcsCalendar;

        foreach ($this->assignments->listForUserInRange($userId, $windowStart, $windowEnd) as $assignment) {
            if ($assignment->status->equals(ScheduleAssignmentStatus::cancelled())) {
                continue;
            }

            $task = $this->tasks->findForUser($userId, $assignment->taskId);
            $summary = $task !== null ? $task->title : 'Scheduled task';

            $calendar->addEvent(
                uid: $this->uid('assignment', $userId, $assignment->startAt, $assignment->endAt, $summary),
                summary: $summary,
                start: $assignment->startAt,
                end: $assignment->endAt,
            );
        }

        foreach ($this->hardLandscape->listForUser($userId) as $event) {
            if ($event->type->equals(HardLandscapeType::recurring()) && $event->recurrence !== null) {
                $this->addRecurring($calendar, $userId, $event->title, $event->startAt, $event->endAt, $event->recurrence, $windowStart, $windowEnd);

                continue;
            }

            if (! $this->overlapsWindow($event->startAt, $event->endAt, $windowStart, $windowEnd)) {
                continue;
            }

            $calendar->addEvent(
                uid: $this->uid('hard-landscape', $userId, $event->startAt, $event->endAt, $event->title),
                summary: $event->title,
                start: $event->startAt,
                end: $event->endAt,
            );
        }

        return $calendar->render();
    }

    private function addRecurring(
        IcsCalendar $calendar,
        int $userId,
        string $title,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        string $recurrence,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
    ): void {
        $durationMinutes = (int) abs($endAt->diffInMinutes($startAt));

        try {
            $rule = RecurrenceRule::parse($recurrence, $startAt);
        } catch (InvalidArgumentException) {
            // Unparseable recurrence degrades to the base event so the block is
            // never silently dropped from the export.
            $calendar->addEvent(
                uid: $this->uid('hard-landscape', $userId, $startAt, $endAt, $title),
                summary: $title,
                start: $startAt,
                end: $endAt,
            );

            return;
        }

        $generator = new RecurrenceOccurrenceGenerator;
        foreach ($generator->generate($rule, $windowStart, $windowEnd) as $occurrence) {
            $occurrenceEnd = $occurrence->addMinutes($durationMinutes);

            $calendar->addEvent(
                uid: $this->uid('hard-landscape', $userId, $occurrence, $occurrenceEnd, $title),
                summary: $title,
                start: $occurrence,
                end: $occurrenceEnd,
            );
        }
    }

    /**
     * Half-open `[start, end)` overlap against the export window.
     */
    private function overlapsWindow(
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
    ): bool {
        return $start->lt($windowEnd) && $end->gt($windowStart);
    }

    /**
     * Stable content-derived UID that never leaks internal database ids.
     */
    private function uid(
        string $kind,
        int $userId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $title,
    ): string {
        $seed = implode('|', [$kind, $userId, $start->toIso8601String(), $end->toIso8601String(), $title]);

        return 'kinevo-'.substr(hash('sha256', $seed), 0, 20).'@kinevo';
    }
}
