<?php

namespace App\Domain\Scheduling\Components;

use App\Domain\Scheduling\Contracts\ScoreComponent;
use App\Domain\Scheduling\RankingCandidate;

/**
 * Priority tier (soft ranking #1, FR-23). Tier 1 > 2 > 3.
 */
final class PriorityTierComponent implements ScoreComponent
{
    public function code(): string
    {
        return 'priority_score';
    }

    public function score(RankingCandidate $candidate): float
    {
        return 4 - $candidate->priorityTier->value;
    }
}
