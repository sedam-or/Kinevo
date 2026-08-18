<?php

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;

/**
 * Duration in whole minutes (domain-model recommended value object).
 * Must be strictly positive.
 */
final class DurationMinutes
{
    public function __construct(
        public readonly int $minutes,
    ) {
        if ($minutes <= 0) {
            throw new InvalidArgumentException('Duration must be greater than zero minutes.');
        }
    }

    public function value(): int
    {
        return $this->minutes;
    }

    public function add(self $other): self
    {
        return new self($this->minutes + $other->minutes);
    }

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }
}
