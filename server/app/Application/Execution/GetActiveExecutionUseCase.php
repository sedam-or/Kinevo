<?php

namespace App\Application\Execution;

use App\Domain\Execution\Contracts\ExecutionSessionRepository;
use App\Domain\Execution\ExecutionSession;

/**
 * The most recent active (running or paused) execution timer for the user
 * (TASK-120). Used to restore the timer after a refresh/browser close (FR-05).
 */
final readonly class GetActiveExecutionUseCase
{
    public function __construct(
        private ExecutionSessionRepository $sessions,
    ) {}

    public function __invoke(int $userId): ?ExecutionSession
    {
        return $this->sessions->findActiveForUser($userId);
    }
}
