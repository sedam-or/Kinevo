<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use App\Domain\Scheduling\ValueObjects\TimeRange;

/**
 * Immutable snapshot of an authoritative schedule: a set of task placements
 * plus its monotonic version. Changing any placement yields a new state with an
 * incremented version.
 */
final class ScheduleState
{
    /**
     * @param  array<string, TimeRange>  $assignments  taskId → slot
     */
    public function __construct(
        public readonly ScheduleVersion $version,
        public readonly array $assignments,
    ) {}

    public function slotFor(string $taskId): ?TimeRange
    {
        return $this->assignments[$taskId] ?? null;
    }

    public function has(string $taskId): bool
    {
        return array_key_exists($taskId, $this->assignments);
    }

    /**
     * @param  array<string, TimeRange>  $assignments
     */
    public function withAssignments(array $assignments): self
    {
        return new self($this->version->next(), $assignments);
    }

    public function isConsistent(): bool
    {
        $slots = array_values($this->assignments);

        foreach ($slots as $i => $a) {
            foreach ($slots as $j => $b) {
                if ($i !== $j && $a->overlaps($b)) {
                    return false;
                }
            }
        }

        return true;
    }
}
