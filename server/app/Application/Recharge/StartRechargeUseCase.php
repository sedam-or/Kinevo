<?php

namespace App\Application\Recharge;

use App\Domain\Recharge\Contracts\RechargeSessionRepository;
use App\Domain\Recharge\RechargeSession;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Start a recharge timer (FR-05). The timer is persisted server-side; only one
 * active (running/paused) recharge session may exist per user at a time.
 */
final readonly class StartRechargeUseCase
{
    public function __construct(
        private RechargeSessionRepository $recharges,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $now): RechargeSession
    {
        if ($this->recharges->findActiveForUser($userId) !== null) {
            throw new InvalidArgumentException('A recharge timer is already running.');
        }

        return $this->recharges->create(RechargeSession::start($userId, $now));
    }
}
