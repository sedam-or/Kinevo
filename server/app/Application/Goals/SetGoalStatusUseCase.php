<?php

namespace App\Application\Goals;

use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Goals\Goal;
use App\Domain\Goals\ValueObjects\GoalStatus;

/**
 * Applies an explicit state transition to a goal (FR-19 archive/completion lifecycle).
 */
final readonly class SetGoalStatusUseCase
{
    public function __construct(
        private GoalRepository $goals,
    ) {}

    public function __invoke(int $userId, int $goalId, GoalStatus $status): Goal
    {
        $goal = (new GetGoalUseCase($this->goals))($userId, $goalId);

        $updated = $goal->withStatus($status);

        return $this->goals->update($updated);
    }
}
