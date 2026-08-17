<?php

namespace App\Application\Goals;

use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Goals\Goal;
use InvalidArgumentException;

/**
 * Returns a single goal scoped to the owner. Not found → InvalidArgumentException.
 */
final readonly class GetGoalUseCase
{
    public function __construct(
        private GoalRepository $goals,
    ) {}

    public function __invoke(int $userId, int $goalId): Goal
    {
        $goal = $this->goals->findForUser($userId, $goalId);

        if ($goal === null) {
            throw new InvalidArgumentException('Goal not found.');
        }

        return $goal;
    }
}
