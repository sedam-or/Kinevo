<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Entities\AiCostAlert;
use Carbon\CarbonImmutable;

interface AiCostAlertRepository
{
    public function create(AiCostAlert $alert): AiCostAlert;

    /** @return array<int, AiCostAlert> */
    public function listUnseenForUser(int $userId, int $limit = 20): array;

    public function markAllSeenForUser(int $userId): int;

    /**
     * Dedupe guard: has a `kind` alert already been recorded since `since`
     * (optionally narrowed to a user and/or threshold)? Prevents re-firing an
     * alert on every request once a threshold is crossed.
     */
    public function existsSince(
        string $kind,
        ?int $userId,
        CarbonImmutable $since,
        ?int $threshold = null,
    ): bool;
}
