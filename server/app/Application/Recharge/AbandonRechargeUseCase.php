<?php

namespace App\Application\Recharge;

use App\Domain\Recharge\Contracts\RechargeSessionRepository;
use App\Domain\Recharge\RechargeSession;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Abandon a recharge timer (FR-05). No recharge duration is recorded.
 */
final readonly class AbandonRechargeUseCase
{
    public function __construct(
        private RechargeSessionRepository $recharges,
    ) {}

    public function __invoke(int $userId, int $sessionId, CarbonImmutable $now): RechargeSession
    {
        $session = $this->recharges->findForUser($userId, $sessionId);

        if ($session === null) {
            throw new InvalidArgumentException('Recharge session not found.');
        }

        return $this->recharges->update($session->abandon($now));
    }
}
