<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use App\Domain\Tasks\Task;

/**
 * Result of an explicit Auto Swap (FR-03). When a Quick Capture task cannot be
 * placed, Auto Swap finds the lowest-priority unlocked task on the target day,
 * moves it to the next day, and places the new task in the vacated slot.
 *
 * `applied` is false when no safe unlocked candidate exists; the new task is
 * never lost and simply remains unplaced with a user-visible explanation.
 */
final readonly class AutoSwapResult
{
    public function __construct(
        public readonly Task $task,
        public readonly bool $applied,
        public readonly ?ScheduleAssignment $assignment,
        public readonly ?Task $swappedTask,
        public readonly ?TimeRange $movedFrom,
        public readonly ?TimeRange $movedTo,
        public readonly string $explanation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'task' => $this->task->toArray(),
            'applied' => $this->applied,
            'assignment' => $this->assignment?->toArray(),
            'swapped_task' => $this->swappedTask?->toArray(),
            'moved_from' => $this->movedFrom !== null
                ? ['start' => $this->movedFrom->start->toISOString(), 'end' => $this->movedFrom->end->toISOString()]
                : null,
            'moved_to' => $this->movedTo !== null
                ? ['start' => $this->movedTo->start->toISOString(), 'end' => $this->movedTo->end->toISOString()]
                : null,
            'explanation' => $this->explanation,
        ];
    }
}
