<?php

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;

/**
 * Closed value object describing how an assignment entered the canonical schedule.
 */
final class ScheduleAssignmentSource
{
    public const DRAFT = 'draft';

    public const MANUAL = 'manual';

    public const RESCHEDULE = 'reschedule';

    public const OVERRIDE = 'override';

    public const QUICK_CAPTURE = 'quick_capture';

    public const AUTO_SWAP = 'auto_swap';

    public const RECURRING = 'recurring';

    private const VALUES = [
        self::DRAFT,
        self::MANUAL,
        self::RESCHEDULE,
        self::OVERRIDE,
        self::QUICK_CAPTURE,
        self::AUTO_SWAP,
        self::RECURRING,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::VALUES, true)) {
            throw new InvalidArgumentException("Invalid schedule assignment source: {$value}");
        }
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
    }

    public static function manual(): self
    {
        return new self(self::MANUAL);
    }

    public static function reschedule(): self
    {
        return new self(self::RESCHEDULE);
    }

    public static function override(): self
    {
        return new self(self::OVERRIDE);
    }

    public static function quickCapture(): self
    {
        return new self(self::QUICK_CAPTURE);
    }

    public static function autoSwap(): self
    {
        return new self(self::AUTO_SWAP);
    }

    public static function recurring(): self
    {
        return new self(self::RECURRING);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
