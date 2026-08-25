<?php

namespace App\Domain\Goals\Contracts;

use App\Domain\Goals\Goal;
use App\Domain\Goals\ValueObjects\GoalHorizon;

interface GoalRepository
{
    public function findForUser(int $userId, int $goalId): ?Goal;

    /**
     * @return array<int, Goal>
     */
    public function listForUser(int $userId): array;

    /**
     * TASK-P19-011 — workspace-scoped listing.
     *
     * @return array<int, Goal>
     */
    public function listForUserInWorkspace(int $userId, int $workspaceId): array;

    public function create(int $userId, Goal $goal): Goal;

    public function update(Goal $goal): Goal;

    /**
     * Count non-terminal goals for a horizon (FR-19/FR-20 active limits).
     */
    public function countActiveForHorizon(int $userId, GoalHorizon $horizon): int;
}
