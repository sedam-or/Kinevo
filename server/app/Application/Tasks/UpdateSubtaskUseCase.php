<?php

namespace App\Application\Tasks;

use App\Domain\Tasks\Contracts\SubtaskRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Subtask;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskProgressCalculator;
use InvalidArgumentException;

/**
 * Updates editable fields of a subtask and recalculates task progress (FR-09).
 */
final readonly class UpdateSubtaskUseCase
{
    public function __construct(
        private SubtaskRepository $subtasks,
        private TaskRepository $tasks,
        private TaskProgressCalculator $calculator,
    ) {}

    public function __invoke(
        int $userId,
        int $subtaskId,
        ?string $title = null,
        ?string $notes = null,
        ?int $sequence = null,
    ): array {
        $subtask = $this->subtasks->findForUser($userId, $subtaskId);

        if ($subtask === null) {
            throw new InvalidArgumentException('Subtask not found.');
        }

        $task = (new GetTaskUseCase($this->tasks))($userId, $subtask->taskId);

        if ($title !== null) {
            $subtask = $subtask->withTitle($title);
        }
        if ($notes !== null) {
            $subtask = $subtask->withNotes($notes);
        }
        if ($sequence !== null) {
            $subtask = $subtask->withSequence($sequence);
        }

        $updated = $this->subtasks->update($subtask);

        $recalculated = $this->tasks->update($task->withProgress($this->recalculateProgress($userId, $task)));

        return ['subtask' => $updated, 'task' => $recalculated];
    }

    private function recalculateProgress(int $userId, Task $task): int
    {
        $subtasks = $this->subtasks->listForTask($userId, $task->id);

        return $this->calculator->calculate($subtasks);
    }
}
