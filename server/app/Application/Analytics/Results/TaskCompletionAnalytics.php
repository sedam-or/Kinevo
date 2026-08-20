<?php

namespace App\Application\Analytics\Results;

/**
 * Task completion read model (TASK-130): snapshot of the user's task board plus
 * how many tasks were completed within the period (from `task_completed`
 * progress events).
 *
 * @phpstan-type StatusCounts array<string, int>
 */
final readonly class TaskCompletionAnalytics
{
    /**
     * @param  StatusCounts  $byStatus
     */
    public function __construct(
        public string $from,
        public string $to,
        public int $totalTasks,
        public int $completedTasks,
        public float $completionRate,
        public int $completedInPeriod,
        public array $byStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'total_tasks' => $this->totalTasks,
            'completed_tasks' => $this->completedTasks,
            'completion_rate' => $this->completionRate,
            'completed_in_period' => $this->completedInPeriod,
            'by_status' => $this->byStatus,
        ];
    }
}
