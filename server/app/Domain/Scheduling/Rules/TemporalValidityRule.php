<?php

namespace App\Domain\Scheduling\Rules;

use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\ConstraintViolation;
use App\Domain\Scheduling\Contracts\HardConstraintRule;
use App\Domain\Scheduling\ScheduleContext;

/**
 * Temporal validity (hard constraint #4). Candidate slot must fall inside the
 * scheduling horizon. Overlap handling is delegated to the overlap rule.
 */
final class TemporalValidityRule implements HardConstraintRule
{
    public function code(): string
    {
        return 'TEMPORAL_VALIDITY';
    }

    public function violations(CandidatePlacement $candidate, ScheduleContext $context): array
    {
        if (! $context->horizon->contains($candidate->slot)) {
            return [new ConstraintViolation(
                $this->code(),
                $candidate->taskId,
                'Candidate slot is outside the scheduling horizon.',
            )];
        }

        return [];
    }
}
