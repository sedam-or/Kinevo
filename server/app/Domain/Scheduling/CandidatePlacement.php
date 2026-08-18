<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\Deadline;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use InvalidArgumentException;

/**
 * A proposed placement of a task into a slot, awaiting feasibility validation
 * (FR-64). Immutable value semantics.
 */
final class CandidatePlacement
{
    public function __construct(
        public readonly string $taskId,
        public readonly string $title,
        public readonly int $durationMinutes,
        public readonly TimeRange $slot,
        public readonly ?Deadline $deadline = null,
        public readonly bool $isLocked = false,
        public readonly bool $isSacredAnchor = false,
        public readonly ?TimeRange $existingSlot = null,
        public readonly ?PriorityTier $priorityTier = null,
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Candidate placement title is required.');
        }
        if ($durationMinutes <= 0) {
            throw new InvalidArgumentException('Candidate duration must be positive.');
        }
    }
}
