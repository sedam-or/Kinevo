<?php

namespace App\Domain\Milestones\ValueObjects;

use InvalidArgumentException;

/**
 * Milestone lifecycle status (SRS §7.3). Explicit transitions; no recursive nesting.
 */
final class MilestoneStatus
{
    public const PLANNED = 'planned';

    public const ACTIVE = 'active';

    public const BLOCKED = 'blocked';

    public const COMPLETED = 'completed';

    public const DROPPED = 'dropped';

    private const STATUSES = [
        self::PLANNED,
        self::ACTIVE,
        self::BLOCKED,
        self::COMPLETED,
        self::DROPPED,
    ];

    /** Explicit allowed transitions keyed by current status. */
    private const TRANSITIONS = [
        self::PLANNED => [self::ACTIVE, self::BLOCKED, self::COMPLETED, self::DROPPED],
        self::ACTIVE => [self::BLOCKED, self::COMPLETED, self::DROPPED],
        self::BLOCKED => [self::ACTIVE, self::COMPLETED, self::DROPPED],
        self::COMPLETED => [],
        self::DROPPED => [],
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unsupported milestone status: {$value}");
        }
    }

    public static function planned(): self
    {
        return new self(self::PLANNED);
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function blocked(): self
    {
        return new self(self::BLOCKED);
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
