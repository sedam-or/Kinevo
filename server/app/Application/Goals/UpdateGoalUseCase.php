<?php

namespace App\Application\Goals;

use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Goals\Goal;
use App\Domain\Goals\ValueObjects\GoalHorizon;
use Carbon\CarbonImmutable;

/**
 * Updates editable fields of an existing goal. Rejects invalid transitions/dates.
 */
final readonly class UpdateGoalUseCase
{
    public function __construct(
        private GoalRepository $goals,
    ) {}

    public function __invoke(
        int $userId,
        int $goalId,
        ?string $title = null,
        ?string $description = null,
        ?GoalHorizon $horizon = null,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $targetDate = null,
        ?string $targetMetric = null,
        ?int $priorityTier = null,
    ): Goal {
        $goal = (new GetGoalUseCase($this->goals))($userId, $goalId);

        if ($title !== null) {
            $goal = $goal->withTitle($title);
        }
        if ($description !== null) {
            $goal = $goal->withDescription($description);
        }
        if ($horizon !== null) {
            $goal = $goal->withHorizon($horizon);
        }
        if ($startDate !== null || $targetDate !== null) {
            $goal = $goal->withDates(
                $startDate ?? $goal->startDate,
                $targetDate ?? $goal->targetDate,
            );
        }
        if ($targetMetric !== null) {
            $goal = $goal->withTargetMetric($targetMetric);
        }
        if ($priorityTier !== null) {
            $goal = $goal->withPriorityTier($priorityTier);
        }

        return $this->goals->update($goal);
    }
}
