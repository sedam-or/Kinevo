<?php

namespace App\Application\Tasks;

use App\Domain\Tasks\Contracts\SubtaskRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskProgressCalculator;
use InvalidArgumentException;

/**
 * Toggles a subtask complete/uncomplete and recalculates the task's derived progress
 * (FR-09: progress = completed / total × 100). No deeper hierarchy (FR-45).
 */
final readonly class ToggleSubtaskUseCase
{
    public function __construct(
        private SubtaskRepository $subtasks,
        private TaskRepository $tasks,
        private TaskProgressCalculator $calculator,
    ) {}

    public function __invoke(int $userId, int $subtaskId): array
    {
        $subtask = $this->subtasks->findForUser($userId, $subtaskId);

        if ($subtask === null) {
            throw new InvalidArgumentException('Subtask not found.');
        }

        $task = (new GetTaskUseCase($this->tasks))($userId, $subtask->taskId);

        $updated = $this->subtasks->update($subtask->withCompleted(! $subtask->completed));

        $recalculated = $this->tasks->update($task->withProgress($this->recalculateProgress($userId, $task)));

        return ['subtask' => $updated, 'task' => $recalculated];
    }

    private function recalculateProgress(int $userId, Task $task): int
    {
        $subtasks = $this->subtasks->listForTask($userId, $task->id);

        return $this->calculator->calculate($subtasks);
    }
}
