<?php

namespace App\Application\Tasks;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Tasks\Contracts\SubtaskRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Subtask;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskProgressCalculator;
use App\Domain\Tasks\ValueObjects\TaskStatus;

/**
 * FR-09 Partial Completion: when incomplete subtasks remain, clone the remaining
 * subtasks + notes into a scheduled continuation Task and mark the original as
 * `continued`. When no subtasks remain, completion is a normal Complete.
 * Appends exactly one activity event per completion (FR-34).
 */
final readonly class PartialCompleteTaskUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private SubtaskRepository $subtasks,
        private TaskProgressCalculator $calculator,
        private RecordActivityUseCase $recordActivity,
    ) {}

    /**
     * @return array{task: Task, continuation: Task|null}
     */
    public function __invoke(int $userId, int $taskId): array
    {
        $task = (new GetTaskUseCase($this->tasks))($userId, $taskId);

        $subtasks = $this->subtasks->listForTask($userId, $taskId);
        $remaining = $this->remaining($subtasks);

        if ($remaining === []) {
            // No remaining subtask: completion becomes complete, not continuation (FR-09).
            $completed = $task->withStatus(TaskStatus::completed());
            $completed = $completed->withProgress(100);

            $saved = $this->tasks->update($completed);

            $this->recordActivity->__invoke(ActivityLog::create(
                $userId,
                ActivityEventType::taskCompleted(),
                'task',
                $saved->id,
                $saved->title,
                operationId: "task:completed:{$saved->id}",
                payload: ['status' => $saved->status->value, 'progress' => $saved->progress],
            ));

            return ['task' => $saved, 'continuation' => null];
        }

        // Schedule continuation (new Task) cloning remaining subtasks + notes.
        $continuation = $this->tasks->create($userId, Task::create(
            $userId,
            $task->title,
            $task->description,
            $task->programId,
            $task->goalId,
            $task->milestoneId,
            $task->priorityTier,
            $task->estimatedMinutes,
            $task->dueAt,
        ));

        foreach ($remaining as $subtask) {
            $this->subtasks->create($userId, Subtask::create(
                $userId,
                $continuation->id,
                $subtask->title,
                $subtask->notes,
                $subtask->sequence,
            ));
        }

        // Mark original as `continued` (FR-09: mark original continued).
        $continued = $task
            ->withStatus(TaskStatus::partial())
            ->withStatus(TaskStatus::continued())
            ->withProgress($this->calculator->calculate($remaining));

        $saved = $this->tasks->update($continued);

        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            ActivityEventType::taskContinued(),
            'task',
            $saved->id,
            $saved->title,
            operationId: "task:continued:{$saved->id}:{$saved->version}",
            payload: [
                'status' => $saved->status->value,
                'progress' => $saved->progress,
                'continuation_task_id' => $continuation->id,
                'remaining_subtasks' => count($remaining),
            ],
        ));

        return ['task' => $saved, 'continuation' => $continuation];
    }

    /**
     * @param  array<int, Subtask>  $subtasks
     * @return array<int, Subtask>
     */
    private function remaining(array $subtasks): array
    {
        return array_values(array_filter(
            $subtasks,
            static fn (Subtask $subtask) => ! $subtask->completed,
        ));
    }
}
