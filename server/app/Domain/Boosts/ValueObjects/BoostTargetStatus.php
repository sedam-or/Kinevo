<?php

namespace App\Domain\Boosts\ValueObjects;

use InvalidArgumentException;

/**
 * Closed set of boost target statuses (SRS FR-37/FR-38). A saved boost target
 * is `active` within its validity period and becomes `ended` when the target is
 * ended early or its break period ends.
 */
final class BoostTargetStatus
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
            throw new InvalidArgumentException("Unsupported boost target status: {$value}");
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
