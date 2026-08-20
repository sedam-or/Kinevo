<?php

namespace App\Application\Boosts;

/**
 * Boost recommendation (SRS FR-37/FR-49). Computed by reusing the Capacity
 * feedback loop; a recommendation above 90% realization with no burnout signal
 * offers Boost Mode. The suggested target is capped at the 70% safety cap.
 */
final readonly class BoostRecommendation
{
    public const NOT_ELIGIBLE = 'NOT_ELIGIBLE';

    public const SUPPRESSED = 'SUPPRESSED';

    public const BOOST_AVAILABLE = 'BOOST_AVAILABLE';

    public const MAINTAIN = 'MAINTAIN';

    public const REDUCE_LOAD = 'REDUCE_LOAD';

    public function __construct(
        public bool $eligible,
        public string $recommendation,
        public string $reason,
        public float $averageRealization,
        public int $recommendedTargetPercent,
    ) {}
}
