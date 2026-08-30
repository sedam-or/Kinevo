<?php

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;

/**
 * Supersession metadata attached when a schedule assignment is archived into
 * `schedule_assignment_history` (ADR-015 history model): which mechanism
 * removed the placement and which schedule version supersedes it (when the
 * replacement is versioned, i.e. draft/reschedule apply).
 */
final class ScheduleSupersession
{
    public function __construct(
        public readonly string $mechanism,
        public readonly ?int $scheduleVersion = null,
        public readonly ?string $reason = null,
    ) {
        if (trim($this->mechanism) === '') {
            throw new InvalidArgumentException('Supersession mechanism must not be empty.');
        }

        if ($this->scheduleVersion !== null && $this->scheduleVersion <= 0) {
            throw new InvalidArgumentException('Supersession schedule version must be positive.');
        }
    }

    public static function draftApply(int $scheduleVersion): self
    {
        return new self('draft', $scheduleVersion);
    }

    public static function rescheduleApply(int $scheduleVersion): self
    {
        return new self('reschedule', $scheduleVersion);
    }
}
