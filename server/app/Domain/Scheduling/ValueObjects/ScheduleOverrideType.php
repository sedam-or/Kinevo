<?php

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;

/**
 * Closed value object for the kind of Schedule Override (FR-25, SRS §7.1
 * `schedule_overrides`).
 *
 * - `permanent`  — Shift Permanen: deactivates the original recurring
 *   occurrence(s) for a selected period and replaces them with the override.
 * - `one_time`   — one-time exception: removes only the selected occurrence and
 *   replaces it with the override interval.
 */
final class ScheduleOverrideType
{
    public const PERMANENT = 'permanent';

    public const ONE_TIME = 'one_time';

    private const VALUES = [
        self::PERMANENT,
        self::ONE_TIME,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::VALUES, true)) {
            throw new InvalidArgumentException("Invalid schedule override type: {$value}");
        }
    }

    public static function permanent(): self
    {
        return new self(self::PERMANENT);
    }

    public static function oneTime(): self
    {
        return new self(self::ONE_TIME);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
