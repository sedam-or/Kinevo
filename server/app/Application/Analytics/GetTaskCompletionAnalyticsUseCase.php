<?php

namespace App\Application\Analytics;

use App\Application\Analytics\Results\TaskCompletionAnalytics;
use App\Domain\ActivityLogs\Contracts\ActivityLogRepository;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\ValueObjects\TaskStatus;
use Carbon\CarbonImmutable;

/**
 * Task completion read model (TASK-130): current board snapshot + task
 * completions recorded within the period.
 */
final readonly class GetTaskCompletionAnalyticsUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private ActivityLogRepository $activityLogs,
    ) {}

    /** TASK-P19-027 — optional workspace scoping (null = global). */
    public function __invoke(int $userId, CarbonImmutable $from, CarbonImmutable $to, ?int $workspaceId = null): TaskCompletionAnalytics
    {
        $all = $workspaceId !== null
            ? $this->tasks->listForUserInWorkspace($userId, $workspaceId)
            : $this->tasks->listForUser($userId);
        $total = count($all);
        $completed = 0;
        $byStatus = [];

        foreach ($all as $task) {
            $byStatus[$task->status->value] = ($byStatus[$task->status->value] ?? 0) + 1;
            if ($task->status->value === TaskStatus::COMPLETED) {
                $completed++;
            }
        }

        $completedInPeriod = 0;
        foreach ($this->activityLogs->exportForUser($userId, $from, $to) as $log) {
            if ($log->eventType->value === ActivityEventType::TASK_COMPLETED) {
                $completedInPeriod++;
            }
        }

        return new TaskCompletionAnalytics(
            $from->toDateString(),
            $to->toDateString(),
            $total,
            $completed,
            $total > 0 ? round($completed / $total, 4) : 0.0,
            $completedInPeriod,
            $byStatus,
        );
    }
}
