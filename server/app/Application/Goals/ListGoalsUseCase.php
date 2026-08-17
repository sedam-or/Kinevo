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
     * @return array<int, Goal>
     */
    public function __invoke(int $userId): array
    {
        return $this->goals->listForUser($userId);
    }
}
