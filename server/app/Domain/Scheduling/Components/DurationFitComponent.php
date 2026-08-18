<?php

namespace App\Domain\Scheduling\Components;

use App\Domain\Scheduling\Contracts\ScoreComponent;
use App\Domain\Scheduling\RankingCandidate;

/**
 * Duration fit (soft ranking #8). An exact slot fit is preferred over a partial
 * one (SLOT_FIT_EXACT). Score 1.0 for exact fit, else the fill ratio.
 */
final class DurationFitComponent implements ScoreComponent
{
    public function code(): string
    {
        return 'duration_fit_score';
    }

    public function score(RankingCandidate $candidate): float
    {
        if ($candidate->slot === null || $candidate->estimatedMinutes === null) {
            return 0.0;
        }

        $slotMinutes = $candidate->slot->durationMinutes()->value();
        if ($slotMinutes <= 0) {
            return 0.0;
        }

        return min(1.0, $candidate->estimatedMinutes / $slotMinutes);
    }
}
