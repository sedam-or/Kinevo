<?php

namespace App\Application\Scheduling;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
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
 * Mini Pause (FR-07). Moves every eligible task scheduled on the given date to
 * the first feasible slot on the following day, respecting hard constraints:
 *
 * - Locked assignments are never auto-moved (FR-04/FR-08).
 * - Terminal tasks (completed/skipped) are never moved.
 * - Candidates must pass the hard-constraint engine (Hard Landscape, deadline,
 *   duration fit, overlap, safety reserve) exactly like any other automation.
 * - Persistence is atomic at the next schedule version; no partial move.
 * - Tasks that cannot be placed next day stay in place and are reported as
 *   visible conflicts (FR-07 exception flow).
 * - The action is logged (FR-34) and the resulting change is explained.
 */
final readonly class MiniPauseUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private ScheduleAssignmentRepository $assignments,
        private HardLandscapeRepository $hardLandscape,
        private SlotCalculator $slots,
        private HardConstraintEngine $constraintEngine,
        private RecordActivityUseCase $recordActivity,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $date): MiniPauseResult
    {
        $today = $this->todayAssignments($userId, $date);

        if ($today === []) {
            return new MiniPauseResult(
                $this->assignments->currentScheduleVersion($userId),
                false,
                [],
                [],
                'No tasks are scheduled on this date, so nothing was moved.',
            );
        }

        $nextDay = $date->addDay();
        $existingNextDay = $this->existingNextDayRanges($userId, $nextDay);
        $landscapeNextDay = $this->landscapeNextDayRanges($userId, $nextDay);
        $occupied = array_merge($existingNextDay, $landscapeNextDay);
        $contextExisting = $existingNextDay;
        $moves = [];
        $conflicts = [];

        foreach ($today as $assignment) {
            $task = $this->tasks->findForUser($userId, $assignment->taskId);

            if ($task === null || $task->status->isTerminal()) {
                continue;
            }

            $targetSlot = $this->findNextDaySlot(
                $nextDay,
                $assignment,
                $task,
                $landscapeNextDay,
                $contextExisting,
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
            $occupied[] = $targetSlot;
            $contextExisting[] = $targetSlot;
        }

        if ($moves === []) {
            return new MiniPauseResult(
                $this->assignments->currentScheduleVersion($userId),
                false,
                [],
                $conflicts,
                $conflicts === []
                    ? 'No eligible tasks could be moved to the next day.'
                    : 'No eligible task could be placed on the next day; all remain in place.',
            );
        }

        $newVersion = $this->assignments->currentScheduleVersion($userId)->next();

        DB::transaction(function () use ($userId, $today, $moves, $nextDay, $newVersion): void {
            $movedByTask = [];
            foreach ($moves as $move) {
                $movedByTask[$move['task_id']] = $move['to'];
            }

            foreach ($today as $assignment) {
                $target = $movedByTask[(string) $assignment->taskId] ?? null;
                if ($target === null) {
                    continue;
                }

                $this->assignments->deleteForUser($userId, $assignment->id);

                $this->assignments->create(ScheduleAssignment::create(
                    userId: $userId,
                    taskId: $assignment->taskId,
                    date: $nextDay,
                    startAt: CarbonImmutable::parse($target['start']),
                    endAt: CarbonImmutable::parse($target['end']),
                    source: ScheduleAssignmentSource::miniPause(),
                    scheduleVersion: $newVersion->value,
                ));
            }
        });

        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            ActivityEventType::miniPause(),
            'schedule',
            $newVersion->value,
            'Mini Pause',
            payload: [
                'date' => $date->toDateString(),
                'moved_task_ids' => array_map(
                    static fn (array $move) => $move['task_id'],
                    $moves,
                ),
                'conflict_task_ids' => $conflicts,
            ],
        ));

        return new MiniPauseResult(
            $newVersion,
            true,
            $moves,
            $conflicts,
            $this->explanation($moves, $conflicts, $nextDay),
        );
    }

    /**
     * @return array<int, ScheduleAssignment>
     */
    private function todayAssignments(int $userId, CarbonImmutable $date): array
    {
        return array_values(array_filter(
            $this->assignments->listForUserOnDate($userId, $date),
            static fn (ScheduleAssignment $a) => ! $a->locked && ! $a->status->equals(
                ScheduleAssignmentStatus::cancelled(),
            ),
        ));
    }

    /**
     * @return array<int, TimeRange>
     */
    private function existingNextDayRanges(int $userId, CarbonImmutable $nextDay): array
    {
        return array_map(
            static fn (ScheduleAssignment $a) => $a->timeRange(),
            $this->assignments->listForUserOnDate($userId, $nextDay),
        );
    }

    /**
     * @return array<int, TimeRange>
     */
    private function landscapeNextDayRanges(int $userId, CarbonImmutable $nextDay): array
    {
        return array_map(
            static fn (HardLandscapeEvent $e) => $e->timeRange(),
            $this->hardLandscape->listForUserOnDate($userId, $nextDay),
        );
    }

    /**
     * Find the first feasible slot on the next day that fits the assignment's
     * duration, considering existing next-day assignments, Hard Landscape, and
     * slots already claimed by other moved tasks in this Mini Pause.
     *
     * @param  array<int, TimeRange>  $landscapeRanges
     * @param  array<int, TimeRange>  $existingRanges
     * @param  array<int, TimeRange>  $occupied
     */
    private function findNextDaySlot(
        CarbonImmutable $nextDay,
        ScheduleAssignment $assignment,
        Task $task,
        array $landscapeRanges,
        array $existingRanges,
        array $occupied,
    ): ?TimeRange {
        $day = new TimeRange($nextDay->startOfDay(), $nextDay->endOfDay());

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

            $context = new ScheduleContext($day, $landscapeRanges, $existingRanges);

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
    private function explanation(array $moves, array $conflicts, CarbonImmutable $nextDay): string
    {
        $titles = array_map(static fn (array $move) => "\"{$move['title']}\"", $moves);

        $parts = [sprintf(
            'Mini Pause: moved %d task(s) to %s: %s.',
            count($moves),
            $nextDay->toDateString(),
            implode(', ', $titles),
        )];

        if ($conflicts !== []) {
            $parts[] = sprintf(
                'Could not place %d task(s) on the next day (no feasible slot): %s.',
                count($conflicts),
                implode(', ', $conflicts),
            );
        }

        return implode(' ', $parts);
    }
}
