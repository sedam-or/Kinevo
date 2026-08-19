<?php

namespace App\Domain\Scheduling;

use RuntimeException;

/**
 * Raised when a persisted assignment would illegally overlap another assignment.
 */
final class ScheduleAssignmentOverlap extends RuntimeException
{
    public function __construct(
        string $message = 'Assignment overlaps with an existing assignment.',
    ) {
        parent::__construct($message);
    }
}
