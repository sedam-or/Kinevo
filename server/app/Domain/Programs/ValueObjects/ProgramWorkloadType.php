<?php

namespace App\Domain\Programs\ValueObjects;

use InvalidArgumentException;

/**
 * Program workload type (FR-26): Structured, Range, Flexible/No Target.
 */
final class ProgramWorkloadType
{
    public const STRUCTURED = 'structured';

    public const RANGE = 'range';

    public const FLEXIBLE = 'flexible';

    private const TYPES = [
        self::STRUCTURED,
        self::RANGE,
        self::FLEXIBLE,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported program workload type: {$value}");
        }
    }

    public static function structured(): self
    {
        return new self(self::STRUCTURED);
    }

    public static function range(): self
    {
        return new self(self::RANGE);
    }

    public static function flexible(): self
    {
        return new self(self::FLEXIBLE);
    }

    /**
     * Structured and Range affect weekly capacity; Flexible does not until tasks are scheduled (FR-26).
     */
    public function affectsWeeklyCapacity(): bool
    {
        return $this->value !== self::FLEXIBLE;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
