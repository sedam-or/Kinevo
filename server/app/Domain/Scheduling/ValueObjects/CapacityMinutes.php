<?php

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;

/**
 * Productive capacity in whole minutes (domain-model recommended VO).
 */
final class CapacityMinutes
{
    public function __construct(
        public readonly int $minutes,
    ) {
        if ($minutes < 0) {
            throw new InvalidArgumentException('Capacity must be zero or positive minutes.');
        }
    }

    public function value(): int
    {
        return $this->minutes;
    }

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }
}
