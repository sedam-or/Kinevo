<?php

namespace App\Application\Execution;

use App\Domain\Execution\Contracts\ExecutionSessionRepository;
use App\Domain\Execution\ExecutionSession;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Pause a running execution timer (TASK-120). Elapsed seconds are banked so the
 * recorded duration is the tracked duration (FR-05).
 */
final readonly class PauseExecutionUseCase
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

        $paused = $session->pause($now);

        return $this->sessions->update($paused);
    }
}
