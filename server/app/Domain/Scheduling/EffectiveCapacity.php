<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\CapacityMinutes;

/**
 * Capacity feedback result (FR-49): the recommended next-week productive load
 * and the deterministic reason. AC-09: 60% realization → ~60–70% of target.
 */
final class EffectiveCapacity
{
    public function __construct(
        public readonly CapacityMinutes $capacityMinutes,
        public readonly float $realizationRatio,
        public readonly string $confidence,
        public readonly string $recommendation,
        public readonly string $reason,
    ) {}
}
