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

    /**
     * List all tasks regardless of user (scheduled jobs, single-owner product).
     *
     * @return array<int, Task>
     */
    public function listAll(): array;

    /**
     * Tasks in `missed` (Terlewat) state for a user — the Morning Recovery
     * candidate set (FR-48).
     *
     * @return array<int, Task>
     */
    public function listMissedForUser(int $userId): array;

    public function create(int $userId, Task $task): Task;

    public function update(Task $task): Task;
}
