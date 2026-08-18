<?php

namespace App\Domain\Scheduling\Rules;

use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\ConstraintViolation;
use App\Domain\Scheduling\Contracts\HardConstraintRule;
use App\Domain\Scheduling\ScheduleContext;

/**
 * Valid duration and slot fit (hard constraint #6). The candidate task duration
 * must fit inside the proposed slot.
 */
final class DurationFitRule implements HardConstraintRule
{
    public function code(): string
    {
        return 'DURATION_FIT';
    }

    public function violations(CandidatePlacement $candidate, ScheduleContext $context): array
    {
        if ($candidate->durationMinutes > $candidate->slot->durationMinutes()->value()) {
            return [new ConstraintViolation(
                $this->code(),
                $candidate->taskId,
                "Task duration ({$candidate->durationMinutes} min) exceeds slot duration "
                    ."({$candidate->slot->durationMinutes()->value()} min).",
            )];
        }

        return [];
    }
}
