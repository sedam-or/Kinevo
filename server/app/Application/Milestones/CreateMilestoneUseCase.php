<?php

namespace App\Application\Milestones;

use App\Application\Goals\GetGoalUseCase;
use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Milestones\Milestone;
use Carbon\CarbonImmutable;

/**
 * Creates a Milestone for exactly one Goal (FR-51). Sequence defaults to next.
 */
final readonly class CreateMilestoneUseCase
{
    public function __construct(
        private MilestoneRepository $milestones,
        private GoalRepository $goals,
    ) {}

    public function __invoke(
        int $userId,
        int $goalId,
        string $title,
        ?string $description,
        ?int $sequence,
        ?CarbonImmutable $targetDate,
        ?int $estimatedMinutes,
    ): Milestone {
        // Milestone belongs to exactly one Goal owned by the user (FR-51, SRS §15.1).
        (new GetGoalUseCase($this->goals))($userId, $goalId);

        $nextSequence = $sequence ?? $this->nextSequence($userId, $goalId);

        $milestone = Milestone::create(
            $goalId,
            $userId,
            $title,
            $description,
            $nextSequence,
            $targetDate,
            $estimatedMinutes,
        );

        return $this->milestones->create($userId, $milestone);
    }

    private function nextSequence(int $userId, int $goalId): int
    {
        $existing = $this->milestones->listForGoal($userId, $goalId);

        $max = 0;
        foreach ($existing as $milestone) {
            $max = max($max, $milestone->sequence);
        }

        return $max + 1;
    }
}
