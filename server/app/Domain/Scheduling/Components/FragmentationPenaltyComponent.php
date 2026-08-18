<?php

namespace App\Domain\Scheduling\Components;

use App\Domain\Scheduling\Contracts\ScoreComponent;
use App\Domain\Scheduling\RankingCandidate;

/**
 * Fragmentation penalty (soft ranking #7). Scheduling produces less fragmented
 * days when higher-penalty (larger) tasks are considered earlier. Higher score
 * = higher fragmentation risk to avoid → score is normalized fragmentation.
 */
final class FragmentationPenaltyComponent implements ScoreComponent
{
    public function code(): string
    {
        return 'fragmentation_penalty';
    }

    public function score(RankingCandidate $candidate): float
    {
        return $candidate->fragmentationPenalty;
    }
}
