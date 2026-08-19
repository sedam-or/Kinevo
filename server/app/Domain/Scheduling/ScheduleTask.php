<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A task eligible for scheduling within a draft run (FR-27 weekly draft).
 */
final class ScheduleTask
{
    public function __construct(
        public readonly string $taskId,
        public readonly string $title,
        public readonly int $durationMinutes,
        public readonly PriorityTier $priorityTier,
        public readonly ?CarbonImmutable $goalDeadline = null,
        public readonly ?CarbonImmutable $milestoneDeadline = null,
        public readonly ?CarbonImmutable $taskDeadline = null,
        public readonly int $progress = 0,
        public readonly ?float $contextFit = null,
        public readonly float $fragmentationPenalty = 0.0,
        public readonly bool $continuityPreference = false,
        public readonly bool $isLocked = false,
        public readonly bool $isSacredAnchor = false,
        public readonly ?TimeRange $existingSlot = null,
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Schedule task title is required.');
        }
        if ($durationMinutes <= 0) {
            throw new InvalidArgumentException('Schedule task duration must be positive.');
        }
    }

    /**
     * Rebuild with an explicit context-fit soft signal (FR-59). Null clears
     * the signal back to the engine-neutral default.
     */
    public function withContextFit(?float $contextFit): self
    {
        return new self(
            $this->taskId,
            $this->title,
            $this->durationMinutes,
            $this->priorityTier,
            $this->goalDeadline,
            $this->milestoneDeadline,
            $this->taskDeadline,
            $this->progress,
            $contextFit,
            $this->fragmentationPenalty,
            $this->continuityPreference,
            $this->isLocked,
            $this->isSacredAnchor,
            $this->existingSlot,
        );
    }
}
