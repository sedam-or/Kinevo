<?php

namespace App\Application\Notifications;

use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\Notification;

/**
 * Mark an in-app notification as read (owner-scoped, SRS §15.1).
 */
final readonly class MarkNotificationReadUseCase
{
    public function __construct(
        private NotificationRepository $notifications,
    ) {}

    public function __invoke(int $userId, int $notificationId): ?Notification
    {
        return $this->notifications->markRead($userId, $notificationId);
    }
}
