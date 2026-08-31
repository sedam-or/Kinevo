<?php

namespace App\Domain\Notifications\ValueObjects;

use InvalidArgumentException;

/**
 * Closed set of in-app notification types (SRS §7 notifications table, FR-35/FR-47).
 */
final class NotificationType
{
    public const RECONCILIATION = 'reconciliation';

    public const BREAK_END = 'break_end';

    public const WEEKLY_DRAFT_READY = 'weekly_draft_ready';

    public const SCHEDULE_NEEDS_REVIEW = 'schedule_needs_review';

    private const TYPES = [
        self::RECONCILIATION,
        self::BREAK_END,
        self::WEEKLY_DRAFT_READY,
        self::SCHEDULE_NEEDS_REVIEW,
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

    public static function breakEnd(): self
    {
        return new self(self::BREAK_END);
    }

    public static function weeklyDraftReady(): self
    {
        return new self(self::WEEKLY_DRAFT_READY);
    }

    public static function scheduleNeedsReview(): self
    {
        return new self(self::SCHEDULE_NEEDS_REVIEW);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
