<?php

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;

/**
 * Closed value object for the kind of Hard Landscape event (SRS §7.1,
 * `hard_landscape_events`). `permanent` is an always-on boundary; `recurring`
 * carries an RRULE (generation semantics owned by TASK-096); `one_time` is a
 * single explicit block/override.
 */
final class HardLandscapeType
{
    public const PERMANENT = 'permanent';

    public const RECURRING = 'recurring';

    public const ONE_TIME = 'one_time';

    private const VALUES = [
        self::PERMANENT,
        self::RECURRING,
        self::ONE_TIME,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::VALUES, true)) {
            throw new InvalidArgumentException("Invalid hard landscape type: {$value}");
        }
    }

    public static function permanent(): self
    {
        return new self(self::PERMANENT);
    }

    public static function recurring(): self
    {
        return new self(self::RECURRING);
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
