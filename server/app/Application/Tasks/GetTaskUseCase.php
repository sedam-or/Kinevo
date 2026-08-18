<?php

namespace App\Application\Tasks;

use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use InvalidArgumentException;

/**
 * Returns a single task scoped to the owner. Not found → InvalidArgumentException.
 */
final readonly class GetTaskUseCase
{
    public function __construct(
        private TaskRepository $tasks,
    ) {}

    public function __invoke(int $userId, int $taskId): Task
    {
        $task = $this->tasks->findForUser($userId, $taskId);

        if ($task === null) {
            throw new InvalidArgumentException('Task not found.');
        }

        return $task;
    }
}
