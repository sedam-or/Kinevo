<?php

namespace App\Domain\ActivityLogs\ValueObjects;

use InvalidArgumentException;

/**
 * Activity event type (FR-34). Completion events for tasks/subtasks.
 */
final class ActivityEventType
{
    public const TASK_COMPLETED = 'task_completed';

    public const TASK_CONTINUED = 'task_continued';

    public const SUBTASK_COMPLETED = 'subtask_completed';

    private const TYPES = [
        self::TASK_COMPLETED,
        self::TASK_CONTINUED,
        self::SUBTASK_COMPLETED,
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

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
