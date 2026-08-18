<?php

namespace App\Domain\Scheduling\Rules;

use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\ConstraintViolation;
use App\Domain\Scheduling\Contracts\HardConstraintRule;
use App\Domain\Scheduling\ScheduleContext;

/**
 * Deadline feasibility (hard constraint #5, §0.3 precedence #6). A candidate
 * must end at or before its task deadline.
 */
final class DeadlineFeasibilityRule implements HardConstraintRule
{
    public function code(): string
    {
        return 'DEADLINE_FEASIBILITY';
    }

    public function violations(CandidatePlacement $candidate, ScheduleContext $context): array
    {
        if ($candidate->deadline !== null && $candidate->slot->end->greaterThan($candidate->deadline->at)) {
            return [new ConstraintViolation(
                $this->code(),
                $candidate->taskId,
                'Candidate slot ends after the task deadline.',
            )];
        }

        return [];
    }
}
