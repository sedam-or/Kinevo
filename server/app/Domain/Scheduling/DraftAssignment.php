<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\TimeRange;

/**
 * A planned placement produced by the auto-schedule draft engine.
 */
final class DraftAssignment
{
    public function __construct(
        public readonly string $taskId,
        public readonly string $title,
        public readonly TimeRange $slot,
    ) {}
}
