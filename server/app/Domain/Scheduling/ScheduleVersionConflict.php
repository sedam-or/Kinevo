<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use RuntimeException;

/**
 * Raised when applying a stale reschedule proposal (FR-28 exception flow).
 * Maps to HTTP 409 SCHEDULE_VERSION_CONFLICT at the boundary.
 */
final class ScheduleVersionConflict extends RuntimeException
{
    public function __construct(
        ScheduleVersion $expectedVersion,
        ScheduleVersion $actualVersion,
    ) {
        parent::__construct(
            "Schedule version conflict: expected {$expectedVersion->value}, got {$actualVersion->value}."
        );
    }
}
