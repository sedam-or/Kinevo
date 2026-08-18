<?php

namespace App\Domain\Scheduling\Rules;

use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\ConstraintViolation;
use App\Domain\Scheduling\Contracts\HardConstraintRule;
use App\Domain\Scheduling\ScheduleContext;

/**
 * No Hard Landscape collision (hard constraint #1, FR-27/FR-04). Automation
 * must never overlap a Hard Landscape block.
 */
final class HardLandscapeCollisionRule implements HardConstraintRule
{
    public function code(): string
    {
        return 'HARD_LANDSCAPE_COLLISION';
    }

    public function violations(CandidatePlacement $candidate, ScheduleContext $context): array
    {
        foreach ($context->hardLandscape as $landscape) {
            if ($candidate->slot->overlaps($landscape)) {
                return [new ConstraintViolation(
                    $this->code(),
                    $candidate->taskId,
                    "Candidate slot overlaps Hard Landscape at {$landscape->start->toDateTimeString()}.",
                )];
            }
        }

        return [];
    }
}
