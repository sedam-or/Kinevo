<?php

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;

/**
 * Closed value object for the persisted state of a task assignment.
 */
final class ScheduleAssignmentStatus
{
    public const SCHEDULED = 'scheduled';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const MISSED = 'missed';

    private const VALUES = [
        self::SCHEDULED,
        self::COMPLETED,
        self::CANCELLED,
        self::MISSED,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::VALUES, true)) {
            throw new InvalidArgumentException("Invalid schedule assignment status: {$value}");
        }
    }

    public static function scheduled(): self
    {
        return new self(self::SCHEDULED);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function missed(): self
    {
        return new self(self::MISSED);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
