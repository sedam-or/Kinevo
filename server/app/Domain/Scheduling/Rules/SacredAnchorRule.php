<?php

namespace App\Domain\Scheduling\Rules;

use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\ConstraintViolation;
use App\Domain\Scheduling\Contracts\HardConstraintRule;
use App\Domain\Scheduling\ScheduleContext;

/**
 * Sacred Anchor rule (hard constraint #3, FR-04). An anchor MUST be exactly
 * 25 minutes, start at/after 06:00, and be locked against automation moves.
 * Placement into the first qualifying slot is the generator's responsibility.
 */
final class SacredAnchorRule implements HardConstraintRule
{
    public const DURATION_MINUTES = 25;

    public const EARLIEST_HOUR = 6;

    public function code(): string
    {
        return 'SACRED_ANCHOR_VIOLATION';
    }

    public function violations(CandidatePlacement $candidate, ScheduleContext $context): array
    {
        if (! $candidate->isSacredAnchor) {
            return [];
        }

        $violations = [];

        if ($candidate->durationMinutes !== self::DURATION_MINUTES) {
            $violations[] = new ConstraintViolation(
                $this->code(),
                $candidate->taskId,
                'Sacred Anchor must be exactly 25 minutes.',
            );
        }

        if ($candidate->slot->start->hour < self::EARLIEST_HOUR) {
            $violations[] = new ConstraintViolation(
                $this->code(),
                $candidate->taskId,
                'Sacred Anchor must start at or after 06:00.',
            );
        }

        if (! $candidate->isLocked) {
            $violations[] = new ConstraintViolation(
                $this->code(),
                $candidate->taskId,
                'Sacred Anchor must be locked against automation.',
            );
        }

        return $violations;
    }
}
