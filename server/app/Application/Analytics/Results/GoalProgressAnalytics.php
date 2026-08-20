<?php

namespace App\Application\Analytics\Results;

/**
 * Goal progress read model (TASK-130/TASK-131): goal/milestone progression,
 * program contribution, deadline health, and workload completion from the
 * user's current goals, milestones, programs, and tasks.
 *
 * @phpstan-type DeadlineHealthCounts array{completed: int, on_track: int, at_risk: int, overdue: int, no_deadline: int}
 */
final readonly class GoalProgressAnalytics
{
    /**
     * @param  array<int, array<string, mixed>>  $goals
     * @param  array<int, array<string, mixed>>  $programs
     * @param  DeadlineHealthCounts  $deadlineHealth
     */
    public function __construct(
        public int $totalGoals,
        public int $completedGoals,
        public float $completionRate,
        public int $totalMilestones,
        public int $completedMilestones,
        public array $goals,
        public array $programs,
        public array $deadlineHealth,
        public int $goalTasksTotal,
        public int $goalTasksCompleted,
        public float $workloadCompletion,
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
            'deadline_health' => $this->deadlineHealth,
            'goal_tasks_total' => $this->goalTasksTotal,
            'goal_tasks_completed' => $this->goalTasksCompleted,
            'workload_completion' => $this->workloadCompletion,
        ];
    }
}
