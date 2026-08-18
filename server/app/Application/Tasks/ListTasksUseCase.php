<?php

namespace App\Application\Tasks;

use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;

/**
 * Lists all tasks of a user.
 */
final readonly class ListTasksUseCase
{
    public function __construct(
        private TaskRepository $tasks,
    ) {}

    /**
     * @return array<int, Task>
     */
    public function __invoke(int $userId): array
    {
        return $this->tasks->listForUser($userId);
    }
}
