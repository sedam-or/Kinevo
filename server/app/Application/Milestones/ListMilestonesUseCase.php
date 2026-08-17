<?php

namespace App\Application\Milestones;

use App\Application\Goals\GetGoalUseCase;
use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Milestones\Milestone;

/**
 * Lists the milestones of one goal, ordered by sequence (FR-51 reorder support).
 */
final readonly class ListMilestonesUseCase
{
    public function __construct(
        private MilestoneRepository $milestones,
        private GoalRepository $goals,
    ) {}

    /**
     * @return array<int, Milestone>
     */
    public function __invoke(int $userId, int $goalId): array
    {
        (new GetGoalUseCase($this->goals))($userId, $goalId);

        return $this->milestones->listForGoal($userId, $goalId);
    }
}
