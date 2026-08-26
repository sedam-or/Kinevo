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
}
