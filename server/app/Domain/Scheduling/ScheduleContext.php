<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\TimeRange;

/**
 * Immutable snapshot of scheduling inputs used by the hard constraint engine:
 * scheduling horizon (day/week), Hard Landscape blocks, existing assignments,
 * and the candidate set under evaluation.
 */
final class ScheduleContext
{
    /**
     * @param  array<int, TimeRange>  $hardLandscape
     * @param  array<int, TimeRange>  $existingAssignments
     * @param  array<int, CandidatePlacement>  $candidates
     */
    public function __construct(
        public readonly TimeRange $horizon,
        public readonly array $hardLandscape = [],
        public readonly array $existingAssignments = [],
        public readonly array $candidates = [],
        public readonly int $reservePercent = 30,
    ) {}
}
