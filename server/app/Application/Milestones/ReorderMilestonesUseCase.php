<?php

namespace App\Application\Milestones;

use App\Application\Goals\GetGoalUseCase;
use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use InvalidArgumentException;

/**
 * Reorders milestones of a goal by applying a new sequence (FR-51 reorder).
 * Only milestones belonging to the goal are touched.
 */
final readonly class ReorderMilestonesUseCase
{
    public function __construct(
        private MilestoneRepository $milestones,
        private GoalRepository $goals,
    ) {}

    /**
     * @param  array<int, int>  $orderedIds  desired order (milestone ids)
     */
    public function __invoke(int $userId, int $goalId, array $orderedIds): void
    {
        (new GetGoalUseCase($this->goals))($userId, $goalId);

        if ($orderedIds === []) {
            return;
        }

        $known = array_map(
            static fn ($m) => $m->id,
            $this->milestones->listForGoal($userId, $goalId),
        );

        foreach ($orderedIds as $id) {
            if (! in_array($id, $known, true)) {
                throw new InvalidArgumentException('Cannot reorder a milestone that does not belong to the goal.');
            }
        }

        $this->milestones->reorder($userId, $goalId, $orderedIds);
    }
}
