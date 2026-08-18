<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\Contracts\HardConstraintRule;
use App\Domain\Scheduling\Rules\DeadlineFeasibilityRule;
use App\Domain\Scheduling\Rules\DurationFitRule;
use App\Domain\Scheduling\Rules\HardLandscapeCollisionRule;
use App\Domain\Scheduling\Rules\IllegalOverlapRule;
use App\Domain\Scheduling\Rules\LockedTaskMoveRule;
use App\Domain\Scheduling\Rules\SacredAnchorRule;
use App\Domain\Scheduling\Rules\SafetyReserveRule;
use App\Domain\Scheduling\Rules\TemporalValidityRule;

/**
 * Hard constraint engine (FR-64). Feasibility validation runs BEFORE any soft
 * scoring; a candidate violating any hard rule is rejected outright. Changes to
 * soft scoring can never make an invalid candidate executable.
 */
final class HardConstraintEngine
{
    /**
     * @param  array<int, HardConstraintRule>  $rules
     */
    public function __construct(
        private readonly array $rules = [],
    ) {}

    public static function default(): self
    {
        return new self([
            new HardLandscapeCollisionRule,
            new LockedTaskMoveRule,
            new SacredAnchorRule,
            new TemporalValidityRule,
            new DeadlineFeasibilityRule,
            new DurationFitRule,
            new IllegalOverlapRule,
            new SafetyReserveRule,
        ]);
    }

    /**
     * Evaluate every rule against every candidate in precedence order.
     * Returns all violations; an empty result means the set is feasible.
     *
     * @param  array<int, CandidatePlacement>  $candidates
     * @return array<int, ConstraintViolation>
     */
    public function validate(ScheduleContext $context, array $candidates): array
    {
        $violations = [];
        $contextWithCandidates = new ScheduleContext(
            $context->horizon,
            $context->hardLandscape,
            $context->existingAssignments,
            $candidates,
            $context->reservePercent,
        );

        foreach ($candidates as $candidate) {
            foreach ($this->rules as $rule) {
                array_push($violations, ...$rule->violations($candidate, $contextWithCandidates));
            }
        }

        return $violations;
    }

    /**
     * @param  array<int, CandidatePlacement>  $candidates
     */
    public function isFeasible(ScheduleContext $context, array $candidates): bool
    {
        return $this->validate($context, $candidates) === [];
    }
}
