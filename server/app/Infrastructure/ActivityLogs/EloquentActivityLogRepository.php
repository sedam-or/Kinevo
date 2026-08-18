<?php

namespace App\Infrastructure\ActivityLogs;

use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\Contracts\ActivityLogRepository;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Models\ActivityLog as ActivityLogModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class EloquentActivityLogRepository implements ActivityLogRepository
{
    public function append(ActivityLog $log): ActivityLog
    {
        $operationId = $log->operationId ?? Str::uuid()->toString();

        $existing = ActivityLogModel::query()
            ->where('user_id', $log->userId)
            ->where('operation_id', $operationId)
            ->first();

        if ($existing !== null) {
            return $this->toDomain($existing);
        }

        $model = ActivityLogModel::query()->create([
            'user_id' => $log->userId,
            'event_type' => $log->eventType->value,
            'entity_type' => $log->entityType,
            'entity_id' => $log->entityId,
            'title' => $log->title,
            'event_at' => $log->eventAt,
            'operation_id' => $operationId,
            'payload' => $log->payload,
        ]);

        return $this->toDomain($model);
    }

    public function listForUser(
        int $userId,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        ?string $eventType = null,
        int $limit = 50,
    ): array {
        $query = ActivityLogModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('event_at');

        if ($from !== null) {
            $query->where('event_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('event_at', '<=', $to);
        }

        if ($eventType !== null) {
            $query->where('event_type', $eventType);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function exportForUser(
        int $userId,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): array {
        $query = ActivityLogModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('event_at');

        if ($from !== null) {
            $query->where('event_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('event_at', '<=', $to);
        }

        return $query
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    private function toDomain(ActivityLogModel $model): ActivityLog
    {
        return new ActivityLog(
            $model->id,
            $model->user_id,
            new ActivityEventType($model->event_type),
            $model->entity_type,
            $model->entity_id,
            $model->title,
            CarbonImmutable::parse($model->event_at),
            $model->operation_id,
            $model->payload ?? [],
        );
    }
}
