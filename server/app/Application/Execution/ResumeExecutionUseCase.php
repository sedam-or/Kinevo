<?php

namespace App\Application\Execution;

use App\Domain\Execution\Contracts\ExecutionSessionRepository;
use App\Domain\Execution\ExecutionSession;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Resume a paused execution timer (TASK-120).
 */
final readonly class ResumeExecutionUseCase
{
    public function __construct(
        private ExecutionSessionRepository $sessions,
    ) {}

    public function __invoke(int $userId, int $sessionId, CarbonImmutable $now): ExecutionSession
    {
        $session = $this->sessions->findForUser($userId, $sessionId);

        if ($session === null) {
            throw new InvalidArgumentException('Execution session not found.');
        }

        $resumed = $session->resume($now);

        return $this->sessions->update($resumed);
    }
}
