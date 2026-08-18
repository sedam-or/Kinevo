<?php

namespace App\Domain\Scheduling\Components;

use App\Domain\Scheduling\Contracts\ScoreComponent;
use App\Domain\Scheduling\RankingCandidate;

/**
 * Progress leverage (soft ranking #5). Near-complete tasks are prioritized:
 * completing a 90%-progress task yields more value per invested minute.
 */
final class ProgressLeverageComponent implements ScoreComponent
{
    public function code(): string
    {
        return 'progress_value_score';
    }

    public function score(RankingCandidate $candidate): float
    {
        return $candidate->progress / 100;
    }
}
