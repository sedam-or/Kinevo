<?php

namespace App\Domain\Breaks\ValueObjects;

use InvalidArgumentException;

/**
 * Closed set of break period statuses (SRS FR-36: confirmed Break Mode period).
 * A confirmed break is `active` until it ends (`ended`). Detection never
 * activates a break without confirmation — the period is only persisted once
 * the user confirms the date range.
 */
final class BreakPeriodStatus
{
    public const ACTIVE = 'active';

    public const ENDED = 'ended';

    private const VALUES = [
        self::ACTIVE,
        self::ENDED,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::VALUES, true)) {
            throw new InvalidArgumentException("Unsupported break period status: {$value}");
        }
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function ended(): self
    {
        return new self(self::ENDED);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
