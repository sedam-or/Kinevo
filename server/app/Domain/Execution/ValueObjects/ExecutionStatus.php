<?php

namespace App\Domain\Execution\ValueObjects;

use InvalidArgumentException;

/**
 * Execution timer status (TASK-120). Persisted state machine; transitions are
 * explicit and validated.
 */
final class ExecutionStatus
{
    public const RUNNING = 'running';

    public const PAUSED = 'paused';

    public const COMPLETED = 'completed';

    public const ABANDONED = 'abandoned';

    private const STATUSES = [
        self::RUNNING,
        self::PAUSED,
        self::COMPLETED,
        self::ABANDONED,
    ];

    /** Explicit allowed transitions keyed by current status. */
    private const TRANSITIONS = [
        self::RUNNING => [self::PAUSED, self::COMPLETED, self::ABANDONED],
        self::PAUSED => [self::RUNNING, self::COMPLETED, self::ABANDONED],
        self::COMPLETED => [],
        self::ABANDONED => [],
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unsupported execution status: {$value}");
        }
    }

    public static function running(): self
    {
        return new self(self::RUNNING);
    }

    public static function paused(): self
    {
        return new self(self::PAUSED);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function abandoned(): self
    {
        return new self(self::ABANDONED);
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value] ?? [], true);
    }

    public function isActive(): bool
    {
        return in_array($this->value, [self::RUNNING, self::PAUSED], true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
