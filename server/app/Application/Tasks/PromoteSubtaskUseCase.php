<?php

namespace App\Application\Tasks;

use App\Domain\Tasks\Contracts\SubtaskRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Subtask;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskProgressCalculator;
use InvalidArgumentException;

/**
 * FR-09 Promote Subtask: removes the child from its original task and creates a
 * standalone Task (default 90 minutes for a promoted heavy task).
 */
final readonly class PromoteSubtaskUseCase
{
    public const DEFAULT_PROMOTED_MINUTES = 90;

    public function __construct(
        private SubtaskRepository $subtasks,
        private TaskRepository $tasks,
        private TaskProgressCalculator $calculator,
    ) {}

    /**
     * @return array{task: Task, subtask: Subtask, source_task: Task}
     */
    public function __invoke(int $userId, int $subtaskId): array
    {
        $subtask = $this->subtasks->findForUser($userId, $subtaskId);

        if ($subtask === null) {
            throw new InvalidArgumentException('Subtask not found.');
        }

        $task = (new GetTaskUseCase($this->tasks))($userId, $subtask->taskId);

        // Remove child from original (FR-09 Alternative: promote removes child from original).
        $this->subtasks->delete($userId, $subtaskId);

        // Create new standalone task; promoted heavy task defaults to 90 minutes (AC-07).
        $promoted = $this->tasks->create($userId, Task::create(
            $userId,
            $subtask->title,
            $subtask->notes,
            $task->programId,
            $task->goalId,
            $task->milestoneId,
            $task->priorityTier,
            $subtask->notes !== null ? self::DEFAULT_PROMOTED_MINUTES : $task->estimatedMinutes,
        ));

        // Recalculate original task progress after the child was removed.
        $recalculated = $this->tasks->update($task->withProgress($this->recalculateProgress($userId, $task)));

        return ['task' => $promoted, 'subtask' => $subtask, 'source_task' => $recalculated];
    }

    private function recalculateProgress(int $userId, Task $task): int
    {
        $subtasks = $this->subtasks->listForTask($userId, $task->id);

        return $this->calculator->calculate($subtasks);
    }
}
