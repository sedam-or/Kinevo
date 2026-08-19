<?php

namespace App\Application\Focus;

use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Focus\FocusSession;

/**
 * List actual focus sessions for the owner (optionally task-scoped).
 */
final readonly class ListFocusSessionsUseCase
{
    public function __construct(
        private FocusSessionRepository $sessions,
    ) {}

    /**
     * @return array<int, FocusSession>
     */
    public function __invoke(int $userId, ?int $taskId = null, int $limit = 50): array
    {
        return $this->sessions->listForUser($userId, $taskId, $limit);
    }
}
