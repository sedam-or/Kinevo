<?php

namespace App\Domain\Scheduling;

/**
 * A ranked candidate with its observable per-component soft scores (used for
 * explainability, scheduling-engine §Explainability contract).
 */
final class RankedCandidate
{
    /**
     * @param  array<string, float>  $components
     */
    public function __construct(
        public readonly RankingCandidate $candidate,
        public readonly array $components,
    ) {}
}
