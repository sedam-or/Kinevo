<?php

namespace App\Domain\Scheduling\ValueObjects;

use InvalidArgumentException;

/**
 * ADR-016 §2.5 — lifecycle of a persisted (weekly) planning draft.
 * pending → applied | discarded | superseded. Staleness is derived, not stored.
 */
final class ScheduleDraftStatus
{
    public const PENDING = 'pending';

    public const APPLIED = 'applied';

    public const DISCARDED = 'discarded';

    public const SUPERSEDED = 'superseded';

    private const STATUSES = [
        self::PENDING,
        self::APPLIED,
        self::DISCARDED,
        self::SUPERSEDED,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unsupported schedule draft status: {$value}");
        }
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function applied(): self
    {
        return new self(self::APPLIED);
    }

    public static function discarded(): self
    {
        return new self(self::DISCARDED);
    }

    public static function superseded(): self
    {
        return new self(self::SUPERSEDED);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
