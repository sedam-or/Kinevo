<?php

namespace App\Domain\Execution\Contracts;

use App\Domain\Execution\ExecutionSession;

interface ExecutionSessionRepository
{
    public function create(ExecutionSession $session): ExecutionSession;

    public function update(ExecutionSession $session): ExecutionSession;

    public function findForUser(int $userId, int $sessionId): ?ExecutionSession;

    /**
     * The most recent active (running or paused) session for the user.
     */
    public function findActiveForUser(int $userId): ?ExecutionSession;

    /**
     * @return array<int, ExecutionSession>
     */
    public function listForUser(int $userId, ?int $taskId = null, int $limit = 50): array;
}
