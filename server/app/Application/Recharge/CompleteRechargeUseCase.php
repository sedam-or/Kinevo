<?php

namespace App\Application\Recharge;

use App\Domain\Recharge\Contracts\RechargeSessionRepository;
use App\Domain\Recharge\RechargeSession;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Complete a recharge timer (FR-05). The recorded duration is the tracked
 * duration (rounded to at least one minute), never the nominal 15 minutes —
 * this duration contributes to RechargeMinutes and the Work-Life Ratio.
 */
final readonly class CompleteRechargeUseCase
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

        return $this->recharges->update($session->complete($now));
    }
}
