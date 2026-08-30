<?php

namespace App\Application\Exports;

use App\Domain\Exports\IcsCalendar;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\Resolution\EffectiveLandscapeResolver;
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
 * - Hard Landscape becomes one VEVENT per EFFECTIVE occurrence (ADR-015):
 *   recurring series are expanded within the selected window and overrides are
 *   applied by the canonical EffectiveLandscapeResolver — the single
 *   recurrence path shared with Today/Week/Month and the scheduler.
 *
 * Internal database identifiers are never exposed: VEVENT UIDs are derived from
 * a content hash and only exportable fields are written (NFR-03).
 */
final readonly class ExportScheduleIcsUseCase
{
    public function __construct(
        private ScheduleAssignmentRepository $assignments,
        private HardLandscapeRepository $hardLandscape,
        private ScheduleOverrideRepository $overrides,
        private EffectiveLandscapeResolver $landscapeResolver,
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

        $resolution = $this->landscapeResolver->resolve(
            $this->hardLandscape->listForUser($userId),
            $this->overrides->listForUser($userId),
            $windowStart,
            $windowEnd,
        );

        foreach ($resolution->occurrences as $occurrence) {
            $calendar->addEvent(
                uid: $this->uid('hard-landscape', $userId, $occurrence->effectiveStart, $occurrence->effectiveEnd, $occurrence->title),
                summary: $occurrence->title,
                start: $occurrence->effectiveStart,
                end: $occurrence->effectiveEnd,
            );
        }

        return $calendar->render();
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
