<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Tasks\Task;

/**
 * Result of a Quick Capture placement attempt (FR-03). The task is always
 * created and returned (a task never disappears); `placed` indicates whether a
 * slot was found and an assignment persisted. When no slot is available the
 * result carries `TASK_NO_CAPACITY` and the three resolution strategies
 * (Manual Swap, Auto Swap, Schedule Later).
 */
final readonly class QuickCaptureResult
{
    public const CODE_PLACED = 'PLACED';

    public const CODE_NO_CAPACITY = 'TASK_NO_CAPACITY';

    public const STRATEGIES = ['manual_swap', 'auto_swap', 'schedule_later'];

    public function __construct(
        public readonly Task $task,
        public readonly bool $placed,
        public readonly ?ScheduleAssignment $assignment,
        public readonly string $code,
        public readonly array $strategies,
    ) {}

    public static function placed(Task $task, ScheduleAssignment $assignment): self
    {
        return new self($task, true, $assignment, self::CODE_PLACED, []);
    }

    public static function noCapacity(Task $task): self
    {
        return new self($task, false, null, self::CODE_NO_CAPACITY, self::STRATEGIES);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'task' => $this->task->toArray(),
            'placed' => $this->placed,
            'assignment' => $this->assignment?->toArray(),
            'code' => $this->code,
            'strategies' => $this->strategies,
        ];
    }
}
