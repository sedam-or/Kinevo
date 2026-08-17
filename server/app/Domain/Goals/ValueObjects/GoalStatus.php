<?php

namespace App\Domain\Goals\ValueObjects;

use InvalidArgumentException;

/**
 * Goal lifecycle status (SRS §7.2). Transitions are explicit and validated.
 */
final class GoalStatus
{
    public const DRAFT = 'draft';

    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    public const COMPLETED = 'completed';

    public const ARCHIVED = 'archived';

    public const DROPPED = 'dropped';

    private const STATUSES = [
        self::DRAFT,
        self::ACTIVE,
        self::PAUSED,
        self::COMPLETED,
        self::ARCHIVED,
        self::DROPPED,
    ];

    /** Explicit allowed transitions keyed by current status. */
    private const TRANSITIONS = [
        self::DRAFT => [self::ACTIVE, self::ARCHIVED, self::DROPPED],
        self::ACTIVE => [self::PAUSED, self::COMPLETED, self::ARCHIVED, self::DROPPED],
        self::PAUSED => [self::ACTIVE, self::COMPLETED, self::ARCHIVED, self::DROPPED],
        self::COMPLETED => [self::ARCHIVED],
        self::ARCHIVED => [],
        self::DROPPED => [],
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unsupported goal status: {$value}");
        }
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
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

    public static function archived(): self
    {
        return new self(self::ARCHIVED);
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
        return in_array($this->value, [self::COMPLETED, self::ARCHIVED, self::DROPPED], true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
