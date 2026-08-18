<?php

namespace App\Domain\Scheduling;

/**
 * Result of an auto-schedule draft run: deterministic assignments plus tasks
 * that could not be placed, each with an explicit reason.
 */
final class ScheduleDraft
{
    /**
     * @param  array<int, DraftAssignment>  $assignments
     * @param  array<int, UnassignedTask>  $unassigned
     */
    public function __construct(
        public readonly array $assignments,
        public readonly array $unassigned,
    ) {}

    /**
     * @return array<int, string>
     */
    public function assignedTaskIds(): array
    {
        return array_map(static fn (DraftAssignment $a) => $a->taskId, $this->assignments);
    }

    public function isComplete(): bool
    {
        return $this->unassigned === [];
    }
}
