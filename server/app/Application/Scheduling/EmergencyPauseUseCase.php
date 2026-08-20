<?php

namespace App\Application\Scheduling;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Pauses\Contracts\PauseEventRepository;
use App\Domain\Pauses\PauseEvent;
use App\Domain\Pauses\ValueObjects\PauseEventType;
use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\HardConstraintEngine;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleContext;
use App\Domain\Scheduling\SlotCalculator;
use App\Domain\Scheduling\ValueObjects\Deadline;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentStatus;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Emergency Pause (FR-07). Tags the current week as exceptional and shifts every
 * eligible task scheduled this week — except the tasks the user chose to keep —
 * to the first feasible slot on the same weekday one week later.
 *
 * - The week is tagged via a `pause_events` record (type emergency), which
 *   marks the exceptional capacity period and drives analytics "grey"
 *   (FR-49 exclusion) and notification suppression (FR-47).
 * - Locked assignments are never auto-moved (FR-04/FR-08 exception flow).
 * - Terminal tasks and cancelled assignments are never moved.
 * - Candidates must pass the hard-constraint engine (Hard Landscape, deadline,
 *   duration fit, overlap, safety reserve) exactly like any other automation.
 * - Tasks that cannot be placed next week stay in place and are reported as
 *   visible conflicts (FR-07 exception flow).
 * - Persistence is atomic at the next schedule version; no partial move.
 * - Task ownership is preserved and no task is ever deleted.
 * - The action is logged (FR-34) and the resulting change is explained.
 */
final readonly class EmergencyPauseUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private ScheduleAssignmentRepository $assignments,
        private HardLandscapeRepository $hardLandscape,
        private PauseEventRepository $pauseEvents,
        private SlotCalculator $slots,
        private HardConstraintEngine $constraintEngine,
        private RecordActivityUseCase $recordActivity,
    ) {}

    /**
     * @param  array<int, int>  $keepTaskIds  tasks the user chose to keep in place
     */
    public function __invoke(int $userId, CarbonImmutable $date, array $keepTaskIds): EmergencyPauseResult
    {
        $weekStart = $date->startOfWeek();
        $weekEnd = $weekStart->addDays(6);
        $weekRange = new TimeRange($weekStart->startOfDay(), $weekEnd->endOfDay());
        $keep = array_map('strval', $keepTaskIds);

        $weekAssignments = $this->weekAssignments($userId, $weekStart, $weekEnd, $keep);

        if ($weekAssignments === []) {
            return new EmergencyPauseResult(
                $this->assignments->currentScheduleVersion($userId),
                false,
                $weekStart->toDateString(),
                $weekEnd->toDateString(),
                $keep,
                [],
                [],
                'No eligible tasks are scheduled this week, so the week was not tagged as an emergency pause.',
            );
        }

        $moves = [];
        $conflicts = [];
        $occupiedByWeek = $this->nextWeekOccupancy($userId, $weekStart);

        foreach ($weekAssignments as $assignment) {
            $task = $this->tasks->findForUser($userId, $assignment->taskId);

            $targetDay = $assignment->date->addWeek();

            $landscapeTarget = $this->landscapeRanges($userId, $targetDay);
            $occupied = array_merge($occupiedByWeek[$targetDay->toDateString()] ?? [], $landscapeTarget);

            $targetSlot = $this->findNextWeekSlot(
                $targetDay,
                $assignment,
                $task,
                $landscapeTarget,
                $occupied,
            );

            if ($targetSlot === null) {
                $conflicts[] = (string) $assignment->taskId;

                continue;
            }

            $moves[] = [
                'task_id' => (string) $assignment->taskId,
                'title' => $task->title,
                'from' => [
                    'start' => $assignment->startAt->toISOString(),
                    'end' => $assignment->endAt->toISOString(),
                ],
                'to' => [
                    'start' => $targetSlot->start->toISOString(),
                    'end' => $targetSlot->end->toISOString(),
                ],
            ];

            $occupiedByWeek[$targetDay->toDateString()][] = $targetSlot;
        }

        $newVersion = $this->assignments->currentScheduleVersion($userId)->next();

        DB::transaction(function () use ($userId, $weekAssignments, $moves, $newVersion): void {
            $movedByTask = [];
            foreach ($moves as $move) {
                $movedByTask[$move['task_id']] = $move['to'];
            }

            foreach ($weekAssignments as $assignment) {
                $target = $movedByTask[(string) $assignment->taskId] ?? null;
                if ($target === null) {
                    continue;
                }

                $this->assignments->deleteForUser($userId, $assignment->id);

                $this->assignments->create(ScheduleAssignment::create(
                    userId: $userId,
                    taskId: $assignment->taskId,
                    date: CarbonImmutable::parse($target['start'])->toDateString(),
                    startAt: CarbonImmutable::parse($target['start']),
                    endAt: CarbonImmutable::parse($target['end']),
                    source: ScheduleAssignmentSource::emergencyPause(),
                    scheduleVersion: $newVersion->value,
                ));
            }
        });

        $movedTaskIds = array_map(static fn (array $move) => $move['task_id'], $moves);

        $this->pauseEvents->create(PauseEvent::create(
            $userId,
            PauseEventType::emergency(),
            $weekStart,
            $weekEnd,
            keepTaskIds: $keep,
            movedTaskIds: $movedTaskIds,
            conflictTaskIds: $conflicts,
            scheduleVersion: $newVersion->value,
        ));

        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            ActivityEventType::emergencyPause(),
            'schedule',
            $newVersion->value,
            'Emergency Pause',
            payload: [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'keep_task_ids' => $keep,
                'moved_task_ids' => $movedTaskIds,
                'conflict_task_ids' => $conflicts,
            ],
        ));

        return new EmergencyPauseResult(
            $newVersion,
            true,
            $weekStart->toDateString(),
            $weekEnd->toDateString(),
            $keep,
            $moves,
            $conflicts,
            $this->explanation($moves, $conflicts, $weekRange),
        );
    }

    /**
     * @param  array<int, string>  $keepTaskIds
     * @return array<int, ScheduleAssignment>
     */
    private function weekAssignments(int $userId, CarbonImmutable $weekStart, CarbonImmutable $weekEnd, array $keepTaskIds): array
    {
        $assignments = array_values(array_filter(
            $this->assignments->listForUserInRange($userId, $weekStart->startOfDay(), $weekEnd->endOfDay()),
            static fn (ScheduleAssignment $a) => ! $a->locked
                && ! $a->status->equals(ScheduleAssignmentStatus::cancelled())
                && ! in_array((string) $a->taskId, $keepTaskIds, true),
        ));

        return array_values(array_filter(
            $assignments,
            fn (ScheduleAssignment $a) => $this->isEligibleTask($userId, $a),
        ));
    }

    private function isEligibleTask(int $userId, ScheduleAssignment $assignment): bool
    {
        $task = $this->tasks->findForUser($userId, $assignment->taskId);

        return $task !== null && ! $task->status->isTerminal();
    }

    /**
     * Occupancy of the following week, keyed by date string: every assignment
     * that already lives on that weekday next week.
     *
     * @return array<string, array<int, TimeRange>>
     */
    private function nextWeekOccupancy(int $userId, CarbonImmutable $weekStart): array
    {
        $nextStart = $weekStart->addWeek();
        $nextEnd = $nextStart->addDays(6);

        $occupancy = [];
        foreach ($this->assignments->listForUserInRange($userId, $nextStart->startOfDay(), $nextEnd->endOfDay()) as $assignment) {
            $occupancy[$assignment->date->toDateString()][] = $assignment->timeRange();
        }

        return $occupancy;
    }

    /**
     * @return array<int, TimeRange>
     */
    private function landscapeRanges(int $userId, CarbonImmutable $day): array
    {
        return array_map(
            static fn (HardLandscapeEvent $e) => $e->timeRange(),
            $this->hardLandscape->listForUserOnDate($userId, $day),
        );
    }

    /**
     * Find the first feasible slot on the target weekday next week that fits
     * the assignment's duration, considering existing assignments, Hard
     * Landscape, and slots already claimed by other moved tasks.
     *
     * @param  array<int, TimeRange>  $landscapeRanges
     * @param  array<int, TimeRange>  $occupied
     */
    private function findNextWeekSlot(
        CarbonImmutable $targetDay,
        ScheduleAssignment $assignment,
        Task $task,
        array $landscapeRanges,
        array $occupied,
    ): ?TimeRange {
        $day = new TimeRange($targetDay->startOfDay(), $targetDay->endOfDay());

        $emptySlots = $this->slots->calculate($day, $occupied);

        foreach ($emptySlots as $emptySlot) {
            if ($emptySlot->durationMinutes()->value() < $assignment->durationMinutes) {
                continue;
            }

            $candidateSlot = new TimeRange(
                $emptySlot->start,
                $emptySlot->start->addMinutes($assignment->durationMinutes),
            );

            $candidate = new CandidatePlacement(
                taskId: (string) $assignment->taskId,
                title: $task->title,
                durationMinutes: $assignment->durationMinutes,
                slot: $candidateSlot,
                deadline: $task->dueAt !== null ? new Deadline($task->dueAt) : null,
                priorityTier: new PriorityTier($task->priorityTier),
            );

            $context = new ScheduleContext($day, $landscapeRanges, []);

            if ($this->constraintEngine->isFeasible($context, [$candidate])) {
                return $candidateSlot;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{task_id: string, title: string, from: array<string, string>|null, to: array<string, string>}>  $moves
     * @param  array<int, string>  $conflicts
     */
    private function explanation(array $moves, array $conflicts, TimeRange $weekRange): string
    {
        $titles = array_map(static fn (array $move) => "\"{$move['title']}\"", $moves);

        $parts = [sprintf(
            'Emergency Pause: %s marked as an exceptional recovery week; moved %d task(s) to the same weekday next week: %s.',
            "{$weekRange->start->toDateString()} to {$weekRange->end->toDateString()}",
            count($moves),
            $titles === [] ? 'none' : implode(', ', $titles),
        )];

        if ($conflicts !== []) {
            $parts[] = sprintf(
                'Could not place %d task(s) next week (no feasible slot); they remain this week and are flagged as conflicts: %s.',
                count($conflicts),
                implode(', ', $conflicts),
            );
        }

        return implode(' ', $parts);
    }
}
