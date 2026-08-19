<?php

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;

/**
 * Explicit placement precedence for schedule resolution (TASK-097; FR-25 /
 * scheduling-engine hard-constraint ordering). When more than one source would
 * occupy a slot, the higher-precedence source wins:
 *
 *   hard landscape > locked task > explicit override > recurrence-generated
 *   event > ordinary generated schedule
 *
 * This is a closed, ordered set. A resolver can compare two precedence levels to
 * decide which placement takes a conflicting slot without mutating either
 * source (overrides never silently rewrite source history).
 */
final class SchedulePrecedence
{
    public const HARD_LANDSCAPE = 'hard_landscape';

    public const LOCKED_TASK = 'locked_task';

    public const EXPLICIT_OVERRIDE = 'explicit_override';

    public const RECURRING = 'recurring';

    public const ORDINARY = 'ordinary';

    /** Ordered lowest → highest precedence (later entries dominate). */
    private const RANK = [
        self::ORDINARY => 0,
        self::RECURRING => 1,
        self::EXPLICIT_OVERRIDE => 2,
        self::LOCKED_TASK => 3,
        self::HARD_LANDSCAPE => 4,
    ];

    private const VALUES = [
        self::ORDINARY,
        self::RECURRING,
        self::EXPLICIT_OVERRIDE,
        self::LOCKED_TASK,
        self::HARD_LANDSCAPE,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::VALUES, true)) {
            throw new InvalidArgumentException("Invalid schedule precedence: {$value}");
        }
    }

    public static function hardLandscape(): self
    {
        return new self(self::HARD_LANDSCAPE);
    }

    public static function lockedTask(): self
    {
        return new self(self::LOCKED_TASK);
    }

    public static function explicitOverride(): self
    {
        return new self(self::EXPLICIT_OVERRIDE);
    }

    public static function recurring(): self
    {
        return new self(self::RECURRING);
    }

    public static function ordinary(): self
    {
        return new self(self::ORDINARY);
    }

    public function rank(): int
    {
        return self::RANK[$this->value];
    }

    /**
     * True when this precedence dominates (should win over) another.
     */
    public function dominates(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
