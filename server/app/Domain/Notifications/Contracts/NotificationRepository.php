<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\Notification;
use App\Domain\Notifications\ValueObjects\NotificationType;
use Carbon\CarbonImmutable;

interface NotificationRepository
{
    /**
     * The reconciliation notification for a given user and local day, or null.
     * FR-47: exactly one reconciliation notification per user/day.
     */
    public function findReconciliationForDay(int $userId, CarbonImmutable $day): ?Notification;

    /**
     * The break-end notification already created for a given break period, or
     * null. FR-39: exactly one H-3 notification per break period.
     */
    public function findBreakEndForPeriod(int $userId, int $breakPeriodId): ?Notification;

    /**
     * A notification of the given type already created for a user and local
     * day, or null. ADR-016 §2.9 — dedup key for schedule attention types.
     */
    public function findForDay(int $userId, NotificationType $type, CarbonImmutable $day): ?Notification;

    public function create(Notification $notification): Notification;

    /**
     * @return array<int, Notification>
     */
    public function listForUser(int $userId, bool $unreadOnly = false, int $limit = 50): array;

    /**
     * Mark a notification read, scoped to its owner. Returns null when the
     * notification does not belong to the user (owner-scoped, SRS §15.1).
     */
    public function markRead(int $userId, int $notificationId): ?Notification;
}
