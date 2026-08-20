<?php

namespace App\Application\Recharge;

use App\Domain\Recharge\Contracts\RechargeSessionRepository;
use App\Domain\Recharge\RechargeSession;

/**
 * List recharge sessions for the user (FR-05 audit/history).
 */
final readonly class ListRechargeSessionsUseCase
{
    public function __construct(
        private RechargeSessionRepository $recharges,
    ) {}

    /**
     * @return array<int, RechargeSession>
     */
    public function __invoke(int $userId, int $limit = 50): array
    {
        return $this->recharges->listForUser($userId, $limit);
    }
}
