<?php

namespace App\Domain\Scheduling\Rules;

use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\ConstraintViolation;
use App\Domain\Scheduling\Contracts\HardConstraintRule;
use App\Domain\Scheduling\ScheduleContext;

/**
 * No illegal overlap (hard constraint #7). A candidate must not overlap existing
 * assignments or other candidates in the same proposal set.
 */
final class IllegalOverlapRule implements HardConstraintRule
{
    public function code(): string
    {
        return 'ILLEGAL_OVERLAP';
    }

    public function violations(CandidatePlacement $candidate, ScheduleContext $context): array
    {
        foreach ($context->existingAssignments as $assignment) {
            if ($candidate->slot->overlaps($assignment)) {
                return [new ConstraintViolation(
                    $this->code(),
                    $candidate->taskId,
                    'Candidate slot overlaps an existing assignment.',
                )];
            }
        }

        foreach ($context->candidates as $other) {
            if ($other->taskId === $candidate->taskId) {
                continue;
            }
            if ($candidate->slot->overlaps($other->slot)) {
                return [new ConstraintViolation(
                    $this->code(),
                    $candidate->taskId,
                    "Candidate slot overlaps another candidate ({$other->taskId}).",
                )];
            }
        }

        return [];
    }
}
