<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A hard-feasible candidate carrying the soft signals used for ranking (FR-23,
 * FR-64). Only candidates that passed the HardConstraintEngine are ranked here.
 */
final class RankingCandidate
{
    public function __construct(
        public readonly string $taskId,
        public readonly PriorityTier $priorityTier,
        public readonly ?CarbonImmutable $goalDeadline = null,
        public readonly ?CarbonImmutable $milestoneDeadline = null,
        public readonly ?CarbonImmutable $taskDeadline = null,
        public readonly int $progress = 0,
        public readonly ?float $contextFit = null,
        public readonly float $fragmentationPenalty = 0.0,
        public readonly ?TimeRange $slot = null,
        public readonly bool $continuityPreference = false,
        public readonly ?int $estimatedMinutes = null,
    ) {
        if ($progress < 0 || $progress > 100) {
            throw new InvalidArgumentException('Progress must be between 0 and 100.');
        }
    }
}
