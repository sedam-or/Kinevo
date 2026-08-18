<?php

namespace App\Domain\Scheduling\Components;

use App\Domain\Scheduling\Contracts\ScoreComponent;
use App\Domain\Scheduling\RankingCandidate;

/**
 * Continuity preference (soft ranking #9). Tasks already started / continued
 * from a previous session are preferred to reduce context switching.
 */
final class ContinuityPreferenceComponent implements ScoreComponent
{
    public function code(): string
    {
        return 'continuity_preference';
    }

    public function score(RankingCandidate $candidate): float
    {
        return $candidate->continuityPreference ? 1.0 : 0.0;
    }
}
