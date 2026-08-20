<?php

namespace App\Application\Analytics\Results;

/**
 * Goal progress read model (TASK-130): goal/milestone progression and program
 * contribution from the user's current goals, milestones, programs, and tasks.
 */
final readonly class GoalProgressAnalytics
{
    /**
     * @param  array<int, array<string, mixed>>  $goals
     * @param  array<int, array<string, mixed>>  $programs
     */
    public function __construct(
        public int $totalGoals,
        public int $completedGoals,
        public float $completionRate,
        public int $totalMilestones,
        public int $completedMilestones,
        public array $goals,
        public array $programs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_goals' => $this->totalGoals,
            'completed_goals' => $this->completedGoals,
            'completion_rate' => $this->completionRate,
            'total_milestones' => $this->totalMilestones,
            'completed_milestones' => $this->completedMilestones,
            'goals' => $this->goals,
            'programs' => $this->programs,
        ];
    }
}
