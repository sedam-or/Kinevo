<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;

/**
 * Result of explicitly applying a reschedule proposal: the new schedule
 * version, the persisted (moved) assignments, whether any mutation actually
 * happened (false on an idempotent retry), and the tasks that could not be
 * placed and remain flagged as visible conflicts (FR-28).
 */
final class RescheduleApplyResult
{
    /**
     * @param  array<int, ScheduleAssignment>  $assignments
     * @param  array<int, string>  $conflictTaskIds
     */
    public function __construct(
        public readonly ScheduleVersion $version,
        public readonly array $assignments,
        public readonly bool $applied,
        public readonly array $conflictTaskIds = [],
    ) {}
}
