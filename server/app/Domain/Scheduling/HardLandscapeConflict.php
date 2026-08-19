<?php

namespace App\Domain\Scheduling;

use InvalidArgumentException;

/**
 * Thrown when a Hard Landscape event would overlap another event belonging to
 * the same user. Overlapping Hard Landscape blocks are rejected (SRS §7.1:
 * "overlap with another hard landscape flagged").
 */
final class HardLandscapeConflict extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('Hard Landscape event overlaps an existing hard landscape event.');
    }
}
