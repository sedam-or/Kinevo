<?php

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\AiCostAlertRepository;

/**
 * TASK-P25-010 — in-app surface for user cost alerts: list unread events for
 * the Settings/AI Usage screen and dismiss them (no notification center yet;
 * the channel is deliberately minimal for the P25-009 scaffold).
 */
final readonly class ListAiCostAlertsUseCase
{
    public function __construct(
        private AiCostAlertRepository $alerts,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function listUnseen(int $userId, int $limit = 20): array
    {
        return array_map(
            static fn ($alert) => $alert->toArray(),
            $this->alerts->listUnseenForUser($userId, $limit),
        );
    }

    public function markAllRead(int $userId): int
    {
        return $this->alerts->markAllSeenForUser($userId);
    }
}
