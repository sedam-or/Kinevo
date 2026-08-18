<?php

namespace App\Domain\Tasks\Contracts;

use App\Domain\Tasks\Task;

interface TaskRepository
{
    public function findForUser(int $userId, int $taskId): ?Task;

    /**
     * @return array<int, Task>
     */
    public function listForUser(int $userId): array;

    public function create(int $userId, Task $task): Task;

    public function update(Task $task): Task;
}
