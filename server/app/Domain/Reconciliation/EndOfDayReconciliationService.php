<?php

namespace App\Domain\Reconciliation;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\ValueObjects\TaskStatus;

/**
 * End-of-Day reconciliation (FR-47, TASK-054).
 *
 * Every day at 21:00 the system prompts on tasks not completed/partial; at
 * 23:59 unresponded eligible tasks become Terlewat (missed). Per the Task
 * state machine (domain-model), only `scheduled` tasks can transition to
 * `missed`, so the deadline reconciliation targets tasks scheduled for the
 * day that were not completed. Transitions are validated by the state machine,
 * making the job idempotent: already-missed/terminal tasks are never
 * re-processed and no duplicate state transition is possible.
 */
final class EndOfDayReconciliationService
{
    /**
     * Whether a task should be included in the 21:00 reconciliation prompt.
     * FR-35/FR-47 scan tasks that are not completed/partial. Only actionable
     * states are prompted: tasks scheduled for the day (scheduled) and tasks
     * currently being worked (in_progress). Already-resolved or terminal states
     * never prompt.
     */
    public function isEligibleForPrompt(Task $task): bool
    {
        return in_array($task->status->value, [TaskStatus::SCHEDULED, TaskStatus::IN_PROGRESS], true);
    }

    /**
     * Filter tasks to those eligible for the 21:00 reconciliation prompt,
     * preserving order.
     *
     * @param  array<int, Task>  $tasks
     * @return array<int, Task>
     */
    public function promptTasks(array $tasks): array
    {
        return array_values(array_filter($tasks, fn (Task $task) => $this->isEligibleForPrompt($task)));
    }

    /**
     * Whether a task is a candidate for the 23:59 deadline reconciliation —
     * i.e. it can legally transition to `missed` (Terlewat) per the state
     * machine and is not already terminal.
     */
    public function isEligibleForDeadline(Task $task): bool
    {
        if ($task->status->isTerminal()) {
            return false;
        }

        return $task->status->canTransitionTo(TaskStatus::missed());
    }

    /**
     * Mark a task as missed (Terlewat) at the 23:59 deadline. Returns the
     * updated task, or the unchanged task if the transition is not allowed
     * (idempotent — no exception, no duplicate transition).
     */
    public function markMissed(Task $task): Task
    {
        if (! $this->isEligibleForDeadline($task)) {
            return $task;
        }

        return $task->withStatus(TaskStatus::missed());
    }

    /**
     * Reconcile a set of tasks at the deadline: transition each eligible task
     * to `missed`. Returns only the tasks that changed (were reconciled),
     * preserving order.
     *
     * @param  array<int, Task>  $tasks
     * @return array<int, Task> reconciled (now-missed) tasks
     */
    public function reconcileAtDeadline(array $tasks): array
    {
        $reconciled = [];
        foreach ($tasks as $task) {
            $missed = $this->markMissed($task);
            if (! $missed->status->equals($task->status)) {
                $reconciled[] = $missed;
            }
        }

        return $reconciled;
    }
}
