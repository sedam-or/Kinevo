<?php

namespace App\Domain\Scheduling\Components;

use App\Domain\Scheduling\Contracts\ScoreComponent;
use App\Domain\Scheduling\RankingCandidate;

/**
 * Task deadline (soft ranking #4; FR-48 recovery priority: nearest deadline
 * first). Higher = closer task deadline.
 */
final class TaskDeadlineComponent implements ScoreComponent
{
    public function code(): string
    {
        return 'task_deadline_score';
    }

    public function score(RankingCandidate $candidate): float
    {
        if ($candidate->taskDeadline === null) {
            return -INF;
        }

        return -$candidate->taskDeadline->getTimestamp();
    }
}
