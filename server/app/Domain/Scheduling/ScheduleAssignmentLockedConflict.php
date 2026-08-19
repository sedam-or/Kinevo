<?php

namespace App\Domain\Scheduling;

use RuntimeException;

/**
 * Raised when a draft/proposal would move or overwrite a locked assignment
 * (FR-04/FR-27: locked tasks are never moved by automation).
 */
final class ScheduleAssignmentLockedConflict extends RuntimeException
{
    public function __construct(
        int $taskId,
    ) {
        parent::__construct("Draft would move the locked assignment for task {$taskId}.");
    }
}
