<?php

namespace App\Domain\Progress\ValueObjects;

use InvalidArgumentException;

/**
 * Meaningful progress event types (SRS §6.8). Closed, domain-owned set.
 */
final class ProgressEventType
{
    public const TASK_COMPLETED = 'task_completed';

    public const MILESTONE_ADVANCED = 'milestone_advanced';

    public const MILESTONE_COMPLETED = 'milestone_completed';

    public const EVIDENCE_ATTACHED = 'evidence_attached';

    public const EXPERIMENT_RECORDED = 'experiment_recorded';

    public const GOAL_PROGRESS = 'goal_progress';

    /** Types that may be recorded manually (not derived from a status change). */
    public const MANUAL_TYPES = [
        self::EVIDENCE_ATTACHED,
        self::EXPERIMENT_RECORDED,
        self::GOAL_PROGRESS,
    ];

    private const TYPES = [
        self::TASK_COMPLETED,
        self::MILESTONE_ADVANCED,
        self::MILESTONE_COMPLETED,
        self::EVIDENCE_ATTACHED,
        self::EXPERIMENT_RECORDED,
        self::GOAL_PROGRESS,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported progress event type: {$value}");
        }
    }

    public static function taskCompleted(): self
    {
        return new self(self::TASK_COMPLETED);
    }

    public static function milestoneAdvanced(): self
    {
        return new self(self::MILESTONE_ADVANCED);
    }

    public static function milestoneCompleted(): self
    {
        return new self(self::MILESTONE_COMPLETED);
    }

    public static function evidenceAttached(): self
    {
        return new self(self::EVIDENCE_ATTACHED);
    }

    public static function experimentRecorded(): self
    {
        return new self(self::EXPERIMENT_RECORDED);
    }

    public static function goalProgress(): self
    {
        return new self(self::GOAL_PROGRESS);
    }

    public function isManual(): bool
    {
        return in_array($this->value, self::MANUAL_TYPES, true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
