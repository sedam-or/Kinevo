<?php

namespace App\Domain\Scheduling\Components;

use App\Domain\Scheduling\Contracts\ScoreComponent;
use App\Domain\Scheduling\RankingCandidate;

/**
 * Milestone urgency (soft ranking #3). Higher = closer milestone deadline.
 */
final class MilestoneUrgencyComponent implements ScoreComponent
{
    public function code(): string
    {
        return 'milestone_score';
    }

    public function score(RankingCandidate $candidate): float
    {
        if ($candidate->milestoneDeadline === null) {
            return -INF;
        }

        return -$candidate->milestoneDeadline->getTimestamp();
    }
}
