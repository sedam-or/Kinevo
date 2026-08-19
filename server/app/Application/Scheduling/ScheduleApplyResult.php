<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;

/**
 * Result of explicitly applying a schedule draft: the new schedule version,
 * the persisted assignments, and whether any mutation actually happened
 * (false on an idempotent retry).
 */
final class ScheduleApplyResult
{
    /**
     * @param  array<int, ScheduleAssignment>  $assignments
     */
    public function __construct(
        public readonly ScheduleVersion $version,
        public readonly array $assignments,
        public readonly bool $applied,
    ) {}
}
