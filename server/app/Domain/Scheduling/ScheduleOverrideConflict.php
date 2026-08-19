<?php

namespace App\Domain\Scheduling;

use InvalidArgumentException;

/**
 * Thrown when a Schedule Override would overlap another override targeting the
 * same source series (FR-25: multiple exceptions are allowed, but overlapping
 * replacements for the same series are rejected).
 */
final class ScheduleOverrideConflict extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('Schedule override overlaps another override for the same source.');
    }
}
