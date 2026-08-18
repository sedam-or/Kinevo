<?php

namespace App\Domain\Tasks\Contracts;

use App\Domain\Tasks\Subtask;

interface SubtaskRepository
{
    public function findForUser(int $userId, int $subtaskId): ?Subtask;

    /**
     * @return array<int, Subtask>
     */
    public function listForTask(int $userId, int $taskId): array;

    public function create(int $userId, Subtask $subtask): Subtask;

    public function update(Subtask $subtask): Subtask;

    public function delete(int $userId, int $subtaskId): void;
}
