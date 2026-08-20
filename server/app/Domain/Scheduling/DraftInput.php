<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\TimeRange;
use InvalidArgumentException;

/**
 * Immutable input for a single auto-schedule draft run (FR-27 weekly draft).
 */
final class DraftInput
{
    /**
     * @param  array<int, TimeRange>  $hardLandscape
     * @param  array<int, TimeRange>  $existingAssignments
     * @param  array<int, ScheduleTask>  $tasks
     */
    public function __construct(
        public readonly TimeRange $horizon,
        public readonly array $hardLandscape = [],
        public readonly array $existingAssignments = [],
        public readonly array $tasks = [],
        public readonly ?ScheduleTask $sacredAnchor = null,
        public readonly int $reservePercent = 30,
        public readonly ?int $dailyCapacityPercent = null,
    ) {
        if ($this->dailyCapacityPercent !== null
            && ($this->dailyCapacityPercent < 1 || $this->dailyCapacityPercent > 100)) {
            throw new InvalidArgumentException('Daily capacity percent must be between 1 and 100.');
        }
    }
}
