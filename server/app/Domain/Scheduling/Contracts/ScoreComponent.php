<?php

namespace App\Domain\Scheduling\Contracts;

use App\Domain\Scheduling\RankingCandidate;

/**
 * A single soft ranking signal (scheduling-engine §Soft scoring). Each
 * component is independently testable and produces a higher-is-better score.
 * Soft components NEVER override hard constraints (FR-64).
 */
interface ScoreComponent
{
    public function code(): string;

    public function score(RankingCandidate $candidate): float;
}
