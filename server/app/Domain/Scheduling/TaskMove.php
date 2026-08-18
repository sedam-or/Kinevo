<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\TimeRange;

/**
 * A single task movement in a reschedule proposal diff (FR-28 preview).
 */
final class TaskMove
{
    public function __construct(
        public readonly string $taskId,
        public readonly string $title,
        public readonly ?TimeRange $fromSlot,
        public readonly TimeRange $toSlot,
    ) {}
}
