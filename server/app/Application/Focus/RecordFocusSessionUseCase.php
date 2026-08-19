<?php

namespace App\Application\Focus;

use App\Application\Tasks\GetTaskUseCase;
use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Focus\FocusSession;
use Carbon\CarbonImmutable;

/**
 * Record an actual completed focus session (SRS §7 focus_sessions, §12.2).
 * The optional task reference is validated for ownership.
 */
final readonly class RecordFocusSessionUseCase
{
    public function __construct(
        private FocusSessionRepository $sessions,
        private GetTaskUseCase $getTask,
    ) {}

    public function __invoke(
        int $userId,
        CarbonImmutable $startedAt,
        CarbonImmutable $endedAt,
        ?int $taskId = null,
    ): FocusSession {
        if ($taskId !== null) {
            $this->getTask->__invoke($userId, $taskId);
        }

        return $this->sessions->create(FocusSession::create($userId, $startedAt, $endedAt, $taskId));
    }
}
