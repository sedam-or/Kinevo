<?php

namespace App\Domain\Reconciliation;

use App\Domain\Programs\Program;
use App\Domain\Tasks\Task;

/**
 * Morning Recovery (FR-48, TASK-055).
 *
 * Presents previous-day Terlewat (missed) tasks before normal daily planning.
 * Pure, deterministic helpers: deadline-first ordering, program invalidation
 * (FR-48 Exception Flow) and the allowed resolution actions per task. State
 * transitions themselves are delegated to the Task state machine.
 */
final class MorningRecoveryService
{
    public const ACTION_RESCHEDULE = 'reschedule';

    public const ACTION_COMPLETE = 'complete';

    public const ACTION_BACKLOG = 'backlog';

    public const INVALID_PROGRAM_COMPLETED = 'program_completed';

    public const INVALID_PROGRAM_DROPPED = 'program_dropped';

    /**
     * FR-48 Exception Flow: a recovered task whose program is Dropped/Completed
     * is no longer valid for rescheduling — only manual disposition applies.
     */
    public function programInvalidReason(Task $task, ?Program $program): ?string
    {
        if ($program === null) {
            return null;
        }

        if ($program->status->value === 'completed') {
            return self::INVALID_PROGRAM_COMPLETED;
        }

        if ($program->status->value === 'dropped') {
            return self::INVALID_PROGRAM_DROPPED;
        }

        return null;
    }

    /**
     * Actions a user may take on a recovered task (FR-48). Reschedule is
     * withheld when the owning program is completed/dropped.
     *
     * @return array<int, string>
     */
    public function allowedActions(Task $task, ?Program $program): array
    {
        if ($this->programInvalidReason($task, $program) !== null) {
            return [self::ACTION_COMPLETE, self::ACTION_BACKLOG];
        }

        return [self::ACTION_RESCHEDULE, self::ACTION_COMPLETE, self::ACTION_BACKLOG];
    }

    /**
     * FR-48 Business Rule: recovery prioritizes nearest deadline first.
     * Tasks with a deadline sort by nearest due_at ascending; tasks without a
     * deadline sort last; equal deadlines fall back to task id for a
     * deterministic order.
     *
     * @param  array<int, Task>  $tasks
     * @return array<int, Task>
     */
    public function sortByDeadline(array $tasks): array
    {
        usort($tasks, static function (Task $a, Task $b): int {
            if ($a->dueAt === null && $b->dueAt === null) {
                return $a->id <=> $b->id;
            }
            if ($a->dueAt === null) {
                return 1;
            }
            if ($b->dueAt === null) {
                return -1;
            }

            $byDeadline = $a->dueAt->getTimestamp() <=> $b->dueAt->getTimestamp();

            return $byDeadline !== 0 ? $byDeadline : ($a->id <=> $b->id);
        });

        return $tasks;
    }
}
