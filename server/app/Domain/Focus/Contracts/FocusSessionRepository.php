<?php

namespace App\Domain\Focus\Contracts;

use App\Domain\Focus\FocusSession;
use Carbon\CarbonImmutable;

interface FocusSessionRepository
{
    public function create(FocusSession $session): FocusSession;

    /**
     * @return array<int, FocusSession>
     */
    public function listForUser(int $userId, ?int $taskId = null, int $limit = 50): array;

    /**
     * Completed sessions started at/after a cutoff (recommendation window).
     *
     * @return array<int, FocusSession>
     */
    public function listSince(int $userId, CarbonImmutable $since, int $limit = 200): array;

    /**
     * Number of completed focus sessions for a user within a period
     * (FR-05 recharge cadence: the second completed session in a relevant day
     * context offers the Recharge timer).
     */
    public function countCompletedBetween(int $userId, CarbonImmutable $start, CarbonImmutable $end): int;

    /**
     * Sum of completed focus-session durations in minutes within a period
     * (productive time for the Work-Life Ratio, FR-05).
     */
    public function sumDurationMinutesBetween(int $userId, CarbonImmutable $start, CarbonImmutable $end): int;
}
