<?php

namespace App\Domain\Goals\ValueObjects;

use InvalidArgumentException;

/**
 * Goal planning horizon (SRS §7.2, FR-50). Not a parent-child hierarchy.
 */
final class GoalHorizon
{
    private const HORIZONS = ['yearly', 'quarterly', 'monthly', 'custom'];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::HORIZONS, true)) {
            throw new InvalidArgumentException("Unsupported goal horizon: {$value}");
        }
    }

    public static function yearly(): self
    {
        return new self('yearly');
    }

    public static function quarterly(): self
    {
        return new self('quarterly');
    }

    public static function monthly(): self
    {
        return new self('monthly');
    }

    public static function custom(): self
    {
        return new self('custom');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
