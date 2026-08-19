<?php

namespace App\Domain\Scheduling;

use RuntimeException;

/**
 * Raised when an assignment update is based on a stale version snapshot.
 */
final class ScheduleAssignmentVersionConflict extends RuntimeException
{
    public function __construct(
        int $expectedVersion,
        ?int $actualVersion = null,
    ) {
        $message = $actualVersion === null
            ? "ScheduleAssignment version conflict: expected {$expectedVersion}, record not found."
            : "ScheduleAssignment version conflict: expected {$expectedVersion}, got {$actualVersion}.";

        parent::__construct($message);
    }
}
