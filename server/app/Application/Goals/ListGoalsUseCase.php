<?php

namespace App\Application\Goals;

use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Goals\Goal;

/**
 * Lists the authenticated owner's goals (SRS §15.1 ownership scoping).
 */
final readonly class ListGoalsUseCase
{
    public function __construct(
        private GoalRepository $goals,
    ) {}

    /**
     * TASK-P19-011 — when $workspaceId is provided the list narrows to that
     * workspace; null returns every goal of the owner (global view).
     *
     * @return array<int, Goal>
     */
    public function __invoke(int $userId, ?int $workspaceId = null): array
    {
        if ($workspaceId !== null) {
            return $this->goals->listForUserInWorkspace($userId, $workspaceId);
        }

        return $this->goals->listForUser($userId);
    }
}
