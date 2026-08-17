<?php

namespace App\Domain\Milestones\Contracts;

use App\Domain\Milestones\Milestone;

interface MilestoneRepository
{
    public function findForUser(int $userId, int $milestoneId): ?Milestone;

    /**
     * @return array<int, Milestone>
     */
    public function listForGoal(int $userId, int $goalId): array;

    public function create(int $userId, Milestone $milestone): Milestone;

    public function update(Milestone $milestone): Milestone;

    /**
     * Reorder milestones for a goal. $orderedIds is the desired sequence.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(int $userId, int $goalId, array $orderedIds): void;
}
