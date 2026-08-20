<?php

namespace App\Domain\Recharge\Contracts;

use App\Domain\Recharge\RechargeSession;
use Carbon\CarbonImmutable;

interface RechargeSessionRepository
{
    public function create(RechargeSession $session): RechargeSession;

    public function update(RechargeSession $session): RechargeSession;

    public function findForUser(int $userId, int $sessionId): ?RechargeSession;

    /**
     * The most recent active (running or paused) session for the user.
     */
    public function findActiveForUser(int $userId): ?RechargeSession;

    /**
     * @return array<int, RechargeSession>
     */
    public function listForUser(int $userId, int $limit = 50): array;

    /**
     * Completed recharge minutes summed for a user within a period (FR-05:
     * recharge contributes to RechargeMinutes / Work-Life Ratio).
     */
    public function sumCompletedMinutesBetween(int $userId, CarbonImmutable $start, CarbonImmutable $end): int;

    /**
     * Number of completed recharge sessions for a user within a period.
     */
    public function countCompletedBetween(int $userId, CarbonImmutable $start, CarbonImmutable $end): int;
}
