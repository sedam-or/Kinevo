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

    private const TYPES = [
        self::TASK_COMPLETED,
        self::TASK_CONTINUED,
        self::SUBTASK_COMPLETED,
        self::TASK_STARTED,
        self::TASK_ABANDONED,
        self::MINI_PAUSE,
        self::EMERGENCY_PAUSE,
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

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
