<?php

namespace App\Application\Goals;

use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Goals\Goal;
use App\Domain\Goals\ValueObjects\GoalHorizon;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Creates a Goal draft, enforcing FR-19/FR-20 active limits per horizon.
 */
final readonly class CreateGoalUseCase
{
    public function __construct(
        private GoalRepository $goals,
    ) {}

    public function __invoke(
        int $userId,
        string $title,
        ?string $description,
        GoalHorizon $horizon,
        ?CarbonImmutable $startDate,
        ?CarbonImmutable $targetDate,
        ?string $targetMetric,
        int $priorityTier = 3,
        ?int $workspaceId = null,
    ): Goal {
        $this->assertWithinActiveLimit($userId, $horizon);

        $goal = Goal::create(
            $userId,
            $title,
            $description,
            $horizon,
            $startDate,
            $targetDate,
            $targetMetric,
            $priorityTier,
        );

        if ($workspaceId !== null) {
            $goal = $goal->withWorkspace($workspaceId);
        }

        return $this->goals->create($userId, $goal);
    }

    private function assertWithinActiveLimit(int $userId, GoalHorizon $horizon): void
    {
        $active = $this->goals->countActiveForHorizon($userId, $horizon);

        if ($horizon->equals(GoalHorizon::yearly()) && $active >= Goal::MAX_YEARLY_ACTIVE) {
            throw new InvalidArgumentException(
                'Maximum of '.Goal::MAX_YEARLY_ACTIVE.' active yearly goals reached.'
            );
        }

        if ($horizon->equals(GoalHorizon::monthly()) && $active >= Goal::MAX_MONTHLY_ACTIVE_PER_MONTH) {
            throw new InvalidArgumentException(
                'Maximum of '.Goal::MAX_MONTHLY_ACTIVE_PER_MONTH.' active monthly goals reached.'
            );
        }
    }
}
