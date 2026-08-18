<?php

namespace App\Domain\Scheduling\Components;

use App\Domain\Scheduling\Contracts\ScoreComponent;
use App\Domain\Scheduling\RankingCandidate;

/**
 * Context fit (soft ranking #6). Adaptive context signal (energy, cognitive
 * fit, flow-fit) normalized to 0..1; null context yields a neutral score.
 */
final class ContextFitComponent implements ScoreComponent
{
    public function code(): string
    {
        return 'context_fit_score';
    }

    public function score(RankingCandidate $candidate): float
    {
        return $candidate->contextFit ?? 0.5;
    }
}
