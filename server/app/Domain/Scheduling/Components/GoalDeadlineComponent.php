<?php

namespace App\Domain\Scheduling\Components;

use App\Domain\Scheduling\Contracts\ScoreComponent;
use App\Domain\Scheduling\RankingCandidate;
use Carbon\CarbonImmutable;

/**
 * Nearest goal deadline (soft ranking #2, FR-23 equal-tier tie-break: nearest
 * Yearly Goal deadline first). Higher = closer deadline.
 */
final class GoalDeadlineComponent implements ScoreComponent
{
    public function code(): string
    {
        return 'goal_deadline_score';
    }

    public function score(RankingCandidate $candidate): float
    {
        return $this->deadlineScore($candidate->goalDeadline);
    }

    protected function deadlineScore(?CarbonImmutable $deadline): float
    {
        if ($deadline === null) {
            return -INF;
        }

        return -$deadline->getTimestamp();
    }
}
