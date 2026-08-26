<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Entities\AiRun;
use Carbon\CarbonImmutable;

interface AiRunRepository
{
    public function record(AiRun $run): AiRun;

    /**
     * @return array<int, AiRun>
     */
    public function listForUser(
        int $userId,
        ?string $proposalType = null,
        int $limit = 50,
    ): array;

    /** TASK-P25-007 — count runs since a boundary (daily request safeguard). */
    public function countSince(int $userId, CarbonImmutable $since, ?string $status = null): int;

    /** TASK-P25-007 — sum estimated cost since a boundary (daily cost safeguard; run nulls count as zero). */
    public function sumEstimatedCostSince(int $userId, CarbonImmutable $since): int;

    /** TASK-P25-010 — sum estimated Kinevo-hosted cost across ALL users since a boundary (ops daily spend alert; run nulls count as zero). */
    public function sumEstimatedCostForAllSince(CarbonImmutable $since): int;

    /**
     * TASK-P25-009 — monthly usage aggregate for a user, split by billing
     * ledger (kinevo / byok). Returns an array with keys:
     * `kinevo_count`, `kinevo_cost_minor`, `byok_count`, `total_count`.
     */
    public function monthlyUsageForUser(int $userId, CarbonImmutable $since): array;

    /**
     * TASK-P25-009 — monthly per-feature breakdown for a user's hosted runs.
     * Returns a list of ['type' => proposal_type, 'count' => int, 'kinevo_cost_minor' => int].
     */
    public function monthlyBreakdown(int $userId, CarbonImmutable $since): array;
}
