<?php

namespace App\Application\Execution;

use App\Domain\Execution\Contracts\ExecutionSessionRepository;
use App\Domain\Execution\ExecutionSession;

/**
 * List execution timer sessions for the owner (optionally task-scoped).
 */
final readonly class ListExecutionSessionsUseCase
{
    public function __construct(
        private ExecutionSessionRepository $sessions,
    ) {}

    /**
     * @return array<int, ExecutionSession>
     */
    public function __invoke(int $userId, ?int $taskId = null, int $limit = 50): array
    {
        return $this->sessions->listForUser($userId, $taskId, $limit);
    }
}
