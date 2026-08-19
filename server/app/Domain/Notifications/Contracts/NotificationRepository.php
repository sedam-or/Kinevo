<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\Notification;
use Carbon\CarbonImmutable;

interface NotificationRepository
{
    /**
     * The reconciliation notification for a given user and local day, or null.
     * FR-47: exactly one reconciliation notification per user/day.
     */
    public function findReconciliationForDay(int $userId, CarbonImmutable $day): ?Notification;

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
