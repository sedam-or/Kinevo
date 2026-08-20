<?php

namespace App\Domain\Pauses\ValueObjects;

use InvalidArgumentException;

/**
 * Closed set of pause event types (SRS §7.1 `pause_events`). Emergency Pause
 * tags a whole week as exceptional; Mini Pause is recorded for completeness of
 * the type, though it operates day-scoped.
 */
final class PauseEventType
{
    public const EMERGENCY = 'emergency';

    public const MINI = 'mini';

    private const VALUES = [
        self::EMERGENCY,
        self::MINI,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::VALUES, true)) {
            throw new InvalidArgumentException("Unsupported pause event type: {$value}");
        }
    }

    public static function emergency(): self
    {
        return new self(self::EMERGENCY);
    }

    public static function mini(): self
    {
        return new self(self::MINI);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
