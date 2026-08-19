<?php

namespace App\Infrastructure\Progress;

use App\Domain\Progress\Contracts\ProgressEventRepository;
use App\Domain\Progress\ProgressEvent;
use App\Domain\Progress\ValueObjects\ProgressEventType;
use App\Models\ProgressEvent as ProgressEventModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class EloquentProgressEventRepository implements ProgressEventRepository
{
    public function append(ProgressEvent $event): ProgressEvent
    {
        $operationId = $event->operationId ?? Str::uuid()->toString();

        $existing = ProgressEventModel::query()
            ->where('user_id', $event->userId)
            ->where('operation_id', $operationId)
            ->first();

        if ($existing !== null) {
            return $this->toDomain($existing);
        }

        $model = ProgressEventModel::query()->create([
            'user_id' => $event->userId,
            'event_type' => $event->eventType->value,
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'title' => $event->title,
            'occurred_at' => $event->occurredAt,
            'operation_id' => $operationId,
            'payload' => $event->payload,
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
        $query = ProgressEventModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($from !== null) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('occurred_at', '<=', $to);
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

    private function toDomain(ProgressEventModel $model): ProgressEvent
    {
        return new ProgressEvent(
            $model->id,
            $model->user_id,
            new ProgressEventType($model->event_type),
            $model->entity_type,
            $model->entity_id,
            $model->title,
            CarbonImmutable::parse($model->occurred_at),
            $model->operation_id,
            $model->payload ?? [],
        );
    }
}
