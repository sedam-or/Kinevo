<?php

namespace App\Domain\ActivityLogs\ValueObjects;

use InvalidArgumentException;

/**
 * Activity event type (FR-34). Completion + execution lifecycle events.
 */
final class ActivityEventType
{
    public const TASK_COMPLETED = 'task_completed';

    public const TASK_CONTINUED = 'task_continued';

    public const SUBTASK_COMPLETED = 'subtask_completed';

    public const TASK_STARTED = 'task_started';

    public const TASK_ABANDONED = 'task_abandoned';

    public const MINI_PAUSE = 'mini_pause';

    public const EMERGENCY_PAUSE = 'emergency_pause';

    public const BREAK_START = 'break_start';

    public const BREAK_END = 'break_end';

    public const BOOST_START = 'boost_start';

    public const BOOST_END = 'boost_end';

    public const SCHEDULE_DRAFT_APPLIED = 'schedule_draft_applied';

    public const SCHEDULE_RESCHEDULE_APPLIED = 'schedule_reschedule_applied';

    public const SCHEDULE_OVERRIDE_APPLIED = 'schedule_override_applied';

    public const ASSIGNMENT_LOCKED = 'assignment_locked';

    public const ASSIGNMENT_UNLOCKED = 'assignment_unlocked';

    private const TYPES = [
        self::TASK_COMPLETED,
        self::TASK_CONTINUED,
        self::SUBTASK_COMPLETED,
        self::TASK_STARTED,
        self::TASK_ABANDONED,
        self::MINI_PAUSE,
        self::EMERGENCY_PAUSE,
        self::BREAK_START,
        self::BREAK_END,
        self::BOOST_START,
        self::BOOST_END,
        self::SCHEDULE_DRAFT_APPLIED,
        self::SCHEDULE_RESCHEDULE_APPLIED,
        self::SCHEDULE_OVERRIDE_APPLIED,
        self::ASSIGNMENT_LOCKED,
        self::ASSIGNMENT_UNLOCKED,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported activity event type: {$value}");
        }
    }

    public static function taskCompleted(): self
    {
        return new self(self::TASK_COMPLETED);
    }

    public static function taskContinued(): self
    {
        return new self(self::TASK_CONTINUED);
    }

    public static function subtaskCompleted(): self
    {
        return new self(self::SUBTASK_COMPLETED);
    }

    public static function taskStarted(): self
    {
        return new self(self::TASK_STARTED);
    }

    public static function taskAbandoned(): self
    {
        return new self(self::TASK_ABANDONED);
    }

    public static function miniPause(): self
    {
        return new self(self::MINI_PAUSE);
    }

    public static function emergencyPause(): self
    {
        return new self(self::EMERGENCY_PAUSE);
    }

    public static function breakStart(): self
    {
        return new self(self::BREAK_START);
    }

    public static function breakEnd(): self
    {
        return new self(self::BREAK_END);
    }

    public static function boostStart(): self
    {
        return new self(self::BOOST_START);
    }

    public static function boostEnd(): self
    {
        return new self(self::BOOST_END);
    }

    public static function scheduleDraftApplied(): self
    {
        return new self(self::SCHEDULE_DRAFT_APPLIED);
    }

    public static function scheduleRescheduleApplied(): self
    {
        return new self(self::SCHEDULE_RESCHEDULE_APPLIED);
    }

    public static function scheduleOverrideApplied(): self
    {
        return new self(self::SCHEDULE_OVERRIDE_APPLIED);
    }

    public static function assignmentLocked(): self
    {
        return new self(self::ASSIGNMENT_LOCKED);
    }

    public static function assignmentUnlocked(): self
    {
        return new self(self::ASSIGNMENT_UNLOCKED);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
