<?php

namespace App\Application\Analytics;

use App\Application\Analytics\Results\GoalProgressAnalytics;
use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Goals\ValueObjects\GoalStatus;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Milestones\ValueObjects\MilestoneStatus;
use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\ValueObjects\TaskStatus;

/**
 * Goal progress read model (TASK-130): goal/milestone progression and program
 * contribution. Read-side only — derives progress from the current domain
 * aggregates, never duplicating their invariants.
 */
final readonly class GetGoalProgressAnalyticsUseCase
{
    public function __construct(
        private GoalRepository $goals,
        private MilestoneRepository $milestones,
        private ProgramRepository $programs,
        private TaskRepository $tasks,
    ) {}

    public function __invoke(int $userId): GoalProgressAnalytics
    {
        $allGoals = $this->goals->listForUser($userId);
        $allTasks = $this->tasks->listForUser($userId);

        $goalRows = [];
        $totalMilestones = 0;
        $completedMilestones = 0;

        foreach ($allGoals as $goal) {
            $milestoneRows = $this->milestones->listForGoal($userId, $goal->id);
            $milestonesTotal = count($milestoneRows);
            $milestonesDone = count(array_filter(
                $milestoneRows,
                static fn ($m) => $m->status->value === MilestoneStatus::COMPLETED,
            ));

            $totalMilestones += $milestonesTotal;
            $completedMilestones += $milestonesDone;

            $goalRows[] = [
                'id' => $goal->id,
                'title' => $goal->title,
                'status' => $goal->status->value,
                'progress' => $goal->progress,
                'milestones_total' => $milestonesTotal,
                'milestones_completed' => $milestonesDone,
            ];
        }

        $programRows = [];
        foreach ($this->programs->listForUser($userId) as $program) {
            $programTasks = array_values(array_filter(
                $allTasks,
                static fn ($task) => $task->programId === $program->id,
            ));

            $programRows[] = [
                'id' => $program->id,
                'name' => $program->name,
                'status' => $program->status->value,
                'tasks_total' => count($programTasks),
                'tasks_completed' => count(array_filter(
                    $programTasks,
                    static fn ($task) => $task->status->value === TaskStatus::COMPLETED,
                )),
            ];
        }

        $totalGoals = count($allGoals);
        $completedGoals = count(array_filter(
            $allGoals,
            static fn ($goal) => $goal->status->value === GoalStatus::COMPLETED,
        ));

        return new GoalProgressAnalytics(
            $totalGoals,
            $completedGoals,
            $totalGoals > 0 ? round($completedGoals / $totalGoals, 4) : 0.0,
            $totalMilestones,
            $completedMilestones,
            $goalRows,
            $programRows,
        );
    }
}
