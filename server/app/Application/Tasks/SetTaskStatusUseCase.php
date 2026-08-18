<?php

namespace App\Application\Tasks;

use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\ValueObjects\TaskStatus;

/**
 * Applies an explicit state transition to a task (FR-09 lifecycle).
 */
final readonly class SetTaskStatusUseCase
{
    public function __construct(
        private TaskRepository $tasks,
    ) {}

    public function __invoke(
        int $userId,
        int $taskId,
        TaskStatus $status,
    ): Task {
        $task = (new GetTaskUseCase($this->tasks))($userId, $taskId);

        $updated = $task->withStatus($status);

        return $this->tasks->update($updated);
    }
}
