<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use InvalidArgumentException;

/**
 * A previewable, versioned reschedule proposal (FR-28). Contains the diff
 * (task movements), the base schedule version it was computed against, and the
 * resulting version. Applying a stale proposal fails with a version conflict.
 */
final class RescheduleProposal
{
    /**
     * @param  array<int, TaskMove>  $moves
     * @param  array<int, string>  $conflictTaskIds  tasks that could not be placed
     */
    public function __construct(
        public readonly ScheduleVersion $baseVersion,
        public readonly ScheduleVersion $newVersion,
        public readonly array $moves,
        public readonly array $conflictTaskIds = [],
    ) {
        if ($newVersion->value !== $baseVersion->value + 1) {
            throw new InvalidArgumentException('New schedule version must be exactly one ahead of the base version.');
        }
    }

    /**
     * Compute the resulting assignments when this proposal is applied.
     *
     * @return array<string, TimeRange>
     */
    public function resultingAssignments(ScheduleState $state): array
    {
        $assignments = $state->assignments;

        foreach ($this->moves as $move) {
            $assignments[$move->taskId] = $move->toSlot;
        }

        return $assignments;
    }

    public function hasChanges(): bool
    {
        return $this->moves !== [];
    }
}
