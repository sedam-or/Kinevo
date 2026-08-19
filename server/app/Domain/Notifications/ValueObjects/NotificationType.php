<?php

namespace App\Domain\Notifications\ValueObjects;

use InvalidArgumentException;

/**
 * Closed set of in-app notification types (SRS §7 notifications table, FR-35/FR-47).
 */
final class NotificationType
{
    public const RECONCILIATION = 'reconciliation';

    private const TYPES = [
        self::RECONCILIATION,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported notification type: {$value}");
        }
    }

    public static function reconciliation(): self
    {
        return new self(self::RECONCILIATION);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
