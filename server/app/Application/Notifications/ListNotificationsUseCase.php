<?php

namespace App\Application\Notifications;

use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\Notification;

/**
 * List in-app notifications for the owner (FR-41/FR-35 in-app prompt surface).
 */
final readonly class ListNotificationsUseCase
{
    public function __construct(
        private NotificationRepository $notifications,
    ) {}

    /**
     * @return array<int, Notification>
     */
    public function __invoke(int $userId, bool $unreadOnly = false, int $limit = 50): array
    {
        return $this->notifications->listForUser($userId, $unreadOnly, $limit);
    }
}
