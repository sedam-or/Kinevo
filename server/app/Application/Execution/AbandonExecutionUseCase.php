<?php

namespace App\Application\Execution;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Execution\Contracts\ExecutionSessionRepository;
use App\Domain\Execution\ExecutionSession;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Abandon an active execution timer (TASK-120). No focus session is recorded;
 * the task stays in its current state (the user may start again). Appends a
 * `task_abandoned` activity event.
 */
final readonly class AbandonExecutionUseCase
{
    public function __construct(
        private ExecutionSessionRepository $sessions,
        private RecordActivityUseCase $recordActivity,
    ) {}

    public function __invoke(int $userId, int $sessionId, CarbonImmutable $now): ExecutionSession
    {
        $session = $this->sessions->findForUser($userId, $sessionId);

        if ($session === null) {
            throw new InvalidArgumentException('Execution session not found.');
        }

        $abandoned = $session->abandon($now);
        $saved = $this->sessions->update($abandoned);

        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            ActivityEventType::taskAbandoned(),
            'task',
            $saved->taskId,
            null,
            operationId: "execution:abandoned:{$saved->id}:{$saved->endedAt->getTimestamp()}",
            payload: ['status' => 'abandoned', 'elapsed_seconds' => $saved->accumulatedSeconds],
        ));

        return $saved;
    }
}
