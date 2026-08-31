<?php

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\Notification;
use App\Domain\Notifications\ValueObjects\NotificationType;
use App\Models\Notification as NotificationModel;
use Carbon\CarbonImmutable;

final class EloquentNotificationRepository implements NotificationRepository
{
    public function findReconciliationForDay(int $userId, CarbonImmutable $day): ?Notification
    {
        $model = NotificationModel::query()
            ->where('user_id', $userId)
            ->where('type', NotificationType::RECONCILIATION)
            ->whereDate('scheduled_for', $day->toDateString())
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function findBreakEndForPeriod(int $userId, int $breakPeriodId): ?Notification
    {
        $model = NotificationModel::query()
            ->where('user_id', $userId)
            ->where('type', NotificationType::BREAK_END)
            ->whereJsonContains('payload', ['break_period_id' => (string) $breakPeriodId])
            ->orderByDesc('id')
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function findForDay(int $userId, NotificationType $type, CarbonImmutable $day): ?Notification
    {
        $model = NotificationModel::query()
            ->where('user_id', $userId)
            ->where('type', $type->value)
            ->whereDate('scheduled_for', $day->toDateString())
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function create(Notification $notification): Notification
    {
        $model = NotificationModel::query()->create([
            'user_id' => $notification->userId,
            'type' => $notification->type->value,
            'scheduled_for' => $notification->scheduledFor?->toDateString(),
            'title' => $notification->title,
            'payload' => $notification->payload,
            'read_at' => $notification->readAt,
        ]);

        return $this->toDomain($model);
    }

    public function listForUser(int $userId, bool $unreadOnly = false, int $limit = 50): array
    {
        $query = NotificationModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function markRead(int $userId, int $notificationId): ?Notification
    {
        $model = NotificationModel::query()
            ->where('user_id', $userId)
            ->whereKey($notificationId)
            ->first();

        if ($model === null) {
            return null;
        }

        if ($model->read_at === null) {
            $model->read_at = now();
            $model->save();
        }

        return $this->toDomain($model);
    }

    private function toDomain(NotificationModel $model): Notification
    {
        return new Notification(
            $model->id,
            $model->user_id,
            new NotificationType($model->type),
            $model->scheduled_for === null ? null : CarbonImmutable::parse($model->scheduled_for),
            $model->title,
            $model->payload ?? [],
            $model->read_at === null ? null : CarbonImmutable::parse($model->read_at),
        );
    }
}
