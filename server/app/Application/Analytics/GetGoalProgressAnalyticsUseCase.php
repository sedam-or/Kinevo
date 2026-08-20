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
use Carbon\CarbonImmutable;

/**
 * Goal progress read model (TASK-130/TASK-131): goal/milestone progression,
 * program contribution, deadline health, and workload completion. Read-side
 * only — derives the metrics from the current domain aggregates.
 */
final readonly class GetGoalProgressAnalyticsUseCase
{
    public function __construct(
        private GoalRepository $goals,
        private MilestoneRepository $milestones,
        private ProgramRepository $programs,
        private TaskRepository $tasks,
    ) {}

    public function __invoke(int $userId, ?CarbonImmutable $now = null): GoalProgressAnalytics
    {
        $reference = $now ?? CarbonImmutable::now();

        $allGoals = $this->goals->listForUser($userId);
        $allTasks = $this->tasks->listForUser($userId);

        $goalRows = [];
        $totalMilestones = 0;
        $completedMilestones = 0;
        $deadlineHealth = [
            'completed' => 0,
            'on_track' => 0,
            'at_risk' => 0,
            'overdue' => 0,
            'no_deadline' => 0,
        ];

        $goalTasksTotal = 0;
        $goalTasksCompleted = 0;

        foreach ($allGoals as $goal) {
            $milestoneRows = $this->milestones->listForGoal($userId, $goal->id);
            $milestonesTotal = count($milestoneRows);
            $milestonesDone = count(array_filter(
                $milestoneRows,
                static fn ($m) => $m->status->value === MilestoneStatus::COMPLETED,
            ));

            $totalMilestones += $milestonesTotal;
            $completedMilestones += $milestonesDone;

            $linkedTasks = array_values(array_filter(
                $allTasks,
                static fn ($task) => $task->goalId === $goal->id,
            ));
            $linkedDone = count(array_filter(
                $linkedTasks,
                static fn ($task) => $task->status->value === TaskStatus::COMPLETED,
            ));

            $goalTasksTotal += count($linkedTasks);
            $goalTasksCompleted += $linkedDone;

            [$health, $daysRemaining] = $this->deadlineHealth($goal->status->value, $goal->progress, $goal->startDate, $goal->targetDate, $reference);
            $deadlineHealth[$health]++;

            $goalRows[] = [
                'id' => $goal->id,
                'title' => $goal->title,
                'status' => $goal->status->value,
                'progress' => $goal->progress,
                'milestones_total' => $milestonesTotal,
                'milestones_completed' => $milestonesDone,
                'tasks_total' => count($linkedTasks),
                'tasks_completed' => $linkedDone,
                'days_remaining' => $daysRemaining,
                'deadline_health' => $health,
            ];
        }

        $programRows = [];
        foreach ($this->programs->listForUser($userId) as $program) {
            $programTasks = array_values(array_filter(
                $allTasks,
                static fn ($task) => $task->programId === $program->id,
            ));
            $programDone = count(array_filter(
                $programTasks,
                static fn ($task) => $task->status->value === TaskStatus::COMPLETED,
            ));
            $total = count($programTasks);

            $programRows[] = [
                'id' => $program->id,
                'name' => $program->name,
                'status' => $program->status->value,
                'tasks_total' => $total,
                'tasks_completed' => $programDone,
                'workload_completion' => $total > 0 ? round($programDone / $total, 4) : 0.0,
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
            $deadlineHealth,
            $goalTasksTotal,
            $goalTasksCompleted,
            $goalTasksTotal > 0 ? round($goalTasksCompleted / $goalTasksTotal, 4) : 0.0,
        );
    }

    /**
     * Deadline health for a goal (TASK-131): timeline-based classification of
     * the goal's progress against its target date. A descriptive schedule
     * indicator — not a health diagnosis.
     *
     * @return array{0: string, 1: int|null} health and days remaining
     */
    private function deadlineHealth(string $status, int $progress, ?CarbonImmutable $start, ?CarbonImmutable $target, CarbonImmutable $now): array
    {
        if ($status === GoalStatus::COMPLETED || $progress >= 100) {
            return ['completed', null];
        }

        if ($target === null) {
            return ['no_deadline', null];
        }

        $daysRemaining = (int) ceil($now->diffInDays($target, false));

        if ($daysRemaining < 0) {
            return ['overdue', $daysRemaining];
        }

        if ($start !== null && $target->gt($start)) {
            $elapsed = max(0.0, $now->diffInSeconds($start) / $target->diffInSeconds($start));
            $expectedProgress = (int) round(min(1.0, $elapsed) * 100);
            if ($progress < $expectedProgress) {
                return ['at_risk', $daysRemaining];
            }
        }

        return ['on_track', $daysRemaining];
    }
}
