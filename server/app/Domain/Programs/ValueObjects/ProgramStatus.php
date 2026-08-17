<?php

namespace App\Domain\Programs\ValueObjects;

use InvalidArgumentException;

/**
 * Program lifecycle status (FR-22). Explicit transitions:
 * Active ↔ Paused, Active → Completed/Dropped, Paused → Completed/Dropped.
 */
final class ProgramStatus
{
    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    public const COMPLETED = 'completed';

    public const DROPPED = 'dropped';

    private const STATUSES = [
        self::ACTIVE,
        self::PAUSED,
        self::COMPLETED,
        self::DROPPED,
    ];

    private const TRANSITIONS = [
        self::ACTIVE => [self::PAUSED, self::COMPLETED, self::DROPPED],
        self::PAUSED => [self::ACTIVE, self::COMPLETED, self::DROPPED],
        self::COMPLETED => [],
        self::DROPPED => [],
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unsupported program status: {$value}");
        }
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function paused(): self
    {
        return new self(self::PAUSED);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function dropped(): self
    {
        return new self(self::DROPPED);
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, [self::COMPLETED, self::DROPPED], true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
