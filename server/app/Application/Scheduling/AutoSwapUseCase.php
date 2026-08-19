<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\HardConstraintEngine;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleContext;
use App\Domain\Scheduling\SlotCalculator;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Explicit Auto Swap (FR-03/FR-23/FR-28). When a Quick Capture task has no
 * capacity on its target day, Auto Swap:
 *
 * 1. selects the lowest-priority *unlocked* task assigned on the target day
 *    (farthest deadline as tie-breaker);
 * 2. places the new task in the vacated slot;
 * 3. moves the swapped-out task to a feasible slot on the following day.
 *
 * Invariants: never move a locked task, never violate Hard Landscape, reuse
 * the hard-constraint engine for feasibility, persist atomically, and return a
 * user-visible explanation. The new task is never deleted when no safe
 * candidate exists.
 */
final readonly class AutoSwapUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private ScheduleAssignmentRepository $assignments,
        private HardLandscapeRepository $hardLandscape,
        private SlotCalculator $slots,
        private HardConstraintEngine $constraintEngine,
    ) {}

    public function __invoke(
        int $userId,
        int $taskId,
        CarbonImmutable $targetDate,
        int $durationMinutes,
    ): AutoSwapResult {
        $task = $this->tasks->findForUser($userId, $taskId);
        if ($task === null) {
            throw new InvalidArgumentException('Task not found.');
        }

        $duration = $durationMinutes > 0 ? $durationMinutes : ($task->estimatedMinutes ?? 45);

        $candidate = $this->selectCandidate($userId, $targetDate);
        if ($candidate === null) {
            return new AutoSwapResult(
                $task,
                false,
                null,
                null,
                null,
                null,
                'No unlocked candidate task was available to swap, so the new task was left unplaced.',
            );
        }

        [$candidateAssignment, $candidateTask] = $candidate;

        if ($candidateAssignment->locked) {
            return new AutoSwapResult(
                $task,
                false,
                null,
                $candidateTask,
                $candidateAssignment->timeRange(),
                null,
                sprintf('Candidate "%s" is locked and cannot be moved, so the new task was left unplaced.', $candidateTask->title),
            );
        }

        $movedTo = $this->findNextDaySlot($userId, $targetDate, $candidateAssignment->durationMinutes, (string) $candidateTask->id);

        if ($movedTo === null) {
            return new AutoSwapResult(
                $task,
                false,
                null,
                $candidateTask,
                $candidateAssignment->timeRange(),
                null,
                "A candidate (\"{$candidateTask->title}\") was found but could not be safely moved to the next day, so the new task was left unplaced.",
            );
        }

        $newVersion = $this->assignments->currentScheduleVersion($userId)->next();

        $assignment = DB::transaction(function () use ($userId, $task, $duration, $candidateAssignment, $candidateTask, $movedTo, $newVersion): ScheduleAssignment {
            // Vacate the candidate's slot before placing the new task in it.
            $this->assignments->deleteForUser($userId, $candidateAssignment->id);

            // Place the new task in the vacated slot.
            $placed = $this->assignments->create(ScheduleAssignment::create(
                userId: $userId,
                taskId: $task->id,
                date: $candidateAssignment->date,
                startAt: $candidateAssignment->startAt,
                endAt: $candidateAssignment->startAt->addMinutes($duration),
                source: ScheduleAssignmentSource::autoSwap(),
                scheduleVersion: $newVersion->value,
            ));

            // Move the candidate to the next-day slot.
            $this->assignments->create(ScheduleAssignment::create(
                userId: $userId,
                taskId: $candidateTask->id,
                date: $movedTo->start,
                startAt: $movedTo->start,
                endAt: $movedTo->end,
                source: ScheduleAssignmentSource::autoSwap(),
                scheduleVersion: $newVersion->value,
            ));

            return $placed;
        });

        $explanation = sprintf(
            'Moved "%s" (priority tier %d) to %s to free a slot for "%s".',
            $candidateTask->title,
            $candidateTask->priorityTier,
            $movedTo->start->toDateString(),
            $task->title,
        );

        return new AutoSwapResult(
            $task,
            true,
            $assignment,
            $candidateTask,
            $candidateAssignment->timeRange(),
            $movedTo,
            $explanation,
        );
    }

    /**
     * Select the best swap candidate: lowest-priority (highest tier number)
     * unlocked task assigned on the target day, farthest deadline as tie-breaker
     * (FR-03 business rules). Locked assignments are kept in the pool so the
     * caller can report why a swap was blocked, but they sort last.
     *
     * @return array{0: ScheduleAssignment, 1: Task}|null
     */
    private function selectCandidate(int $userId, CarbonImmutable $date): ?array
    {
        $assignments = $this->assignments->listForUserOnDate($userId, $date);

        $candidates = [];
        foreach ($assignments as $assignment) {
            $task = $this->tasks->findForUser($userId, $assignment->taskId);
            if ($task === null) {
                continue;
            }

            $candidates[] = [$assignment, $task];
        }

        usort($candidates, function (array $a, array $b): int {
            /** @var ScheduleAssignment $assignmentA */
            $assignmentA = $a[0];
            /** @var ScheduleAssignment $assignmentB */
            $assignmentB = $b[0];
            /** @var Task $taskA */
            $taskA = $a[1];
            /** @var Task $taskB */
            $taskB = $b[1];

            // Unlocked candidates sort before locked ones.
            $lockedCmp = $assignmentA->locked <=> $assignmentB->locked;
            if ($lockedCmp !== 0) {
                return $lockedCmp;
            }

            // Lowest priority first (higher tier number = lower priority).
            $priorityCmp = $taskB->priorityTier <=> $taskA->priorityTier;
            if ($priorityCmp !== 0) {
                return $priorityCmp;
            }

            // Farthest (latest) deadline first; a missing deadline counts as farthest.
            return $this->deadlineRank($taskB) <=> $this->deadlineRank($taskA);
        });

        return $candidates[0] ?? null;
    }

    private function deadlineRank(Task $task): string
    {
        // Farthest (latest) deadline first; a missing deadline counts as farthest.
        if ($task->dueAt === null) {
            return '9999-12-31';
        }

        return $task->dueAt->toDateString();
    }

    private function findNextDaySlot(int $userId, CarbonImmutable $date, int $duration, string $taskId): ?TimeRange
    {
        $nextDay = $date->addDay();
        $existing = $this->assignments->listForUserOnDate($userId, $nextDay);
        $landscape = $this->hardLandscape->listForUserOnDate($userId, $nextDay);

        $assignmentRanges = array_map(static fn (ScheduleAssignment $a) => $a->timeRange(), $existing);
        $landscapeRanges = array_map(static fn (HardLandscapeEvent $e) => $e->timeRange(), $landscape);
        $occupied = array_merge($assignmentRanges, $landscapeRanges);

        $day = new TimeRange($nextDay->startOfDay(), $nextDay->endOfDay());
        $emptySlots = $this->slots->calculate($day, $occupied);

        foreach ($emptySlots as $emptySlot) {
            if ($emptySlot->durationMinutes()->value() < $duration) {
                continue;
            }

            $candidateSlot = new TimeRange($emptySlot->start, $emptySlot->start->addMinutes($duration));
            $candidate = new CandidatePlacement(
                taskId: $taskId,
                title: 'Swap',
                durationMinutes: $duration,
                slot: $candidateSlot,
                priorityTier: new PriorityTier(3),
            );

            $context = new ScheduleContext($day, $landscapeRanges, $assignmentRanges);

            if ($this->constraintEngine->isFeasible($context, [$candidate])) {
                return $candidateSlot;
            }
        }

        return null;
    }
}
