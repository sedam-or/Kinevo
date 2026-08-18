<?php

namespace App\Domain\Scheduling\Rules;

use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\ConstraintViolation;
use App\Domain\Scheduling\Contracts\HardConstraintRule;
use App\Domain\Scheduling\ScheduleContext;

/**
 * Do not move locked task through automation (hard constraint #2, FR-04/FR-27).
 * A candidate that relocates a locked task from an existing slot is rejected.
 */
final class LockedTaskMoveRule implements HardConstraintRule
{
    public function code(): string
    {
        return 'LOCKED_TASK_MOVE';
    }

    public function violations(CandidatePlacement $candidate, ScheduleContext $context): array
    {
        if (! $candidate->isLocked || $candidate->existingSlot === null) {
            return [];
        }

        if (! $candidate->existingSlot->equals($candidate->slot)) {
            return [new ConstraintViolation(
                $this->code(),
                $candidate->taskId,
                'Automation must not move a locked task.',
            )];
        }

        return [];
    }
}
