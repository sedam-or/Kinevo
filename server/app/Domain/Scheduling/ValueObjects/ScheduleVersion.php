<?php

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;

/**
 * Monotonic schedule version (domain-model recommended VO; scheduling-engine
 * §Schedule versioning). Applying a stale proposal MUST fail with a conflict.
 */
final class ScheduleVersion
{
    public function __construct(
        public readonly int $value,
    ) {
        if ($value <= 0) {
            throw new InvalidArgumentException('Schedule version must be positive.');
        }
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
