<?php

namespace App\Domain\Scheduling\Contracts;

use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\ConstraintViolation;
use App\Domain\Scheduling\ScheduleContext;

/**
 * A single hard feasibility rule (FR-64). Rules are evaluated before any soft
 * scoring; a violation rejects the candidate outright.
 */
interface HardConstraintRule
{
    public function code(): string;

    /**
     * @return array<int, ConstraintViolation>
     */
    public function violations(CandidatePlacement $candidate, ScheduleContext $context): array;
}
