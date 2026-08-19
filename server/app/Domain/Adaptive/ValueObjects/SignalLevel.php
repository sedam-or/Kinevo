<?php

namespace App\Domain\Adaptive\ValueObjects;

use InvalidArgumentException;

/**
 * Subjective 1–10 self-report signal level (FR-58, SRS §7.6). Advisory only —
 * SHALL NOT be represented as a clinical or neurological measurement.
 */
final class SignalLevel
{
    public const MIN = 1;

    public const MAX = 10;

    public function __construct(
        public readonly int $value,
    ) {
        if ($value < self::MIN || $value > self::MAX) {
            throw new InvalidArgumentException("Signal level must be between 1 and 10, got {$value}.");
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
