<?php

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;

/**
 * Task priority tier 1..3 (SRS priority tiers; Task aggregate uses same range).
 * Lower value = higher priority.
 */
final class PriorityTier
{
    public function __construct(
        public readonly int $value,
    ) {
        if ($value < 1 || $value > 3) {
            throw new InvalidArgumentException('Priority tier must be between 1 and 3.');
        }
    }

    public static function p1(): self
    {
        return new self(1);
    }

    public static function p2(): self
    {
        return new self(2);
    }

    public static function p3(): self
    {
        return new self(3);
    }

    public function isHigherThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
