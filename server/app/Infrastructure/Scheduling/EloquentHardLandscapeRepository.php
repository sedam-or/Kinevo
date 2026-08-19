<?php

namespace App\Infrastructure\Scheduling;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\HardLandscapeConflict;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Models\HardLandscapeEvent as HardLandscapeEventModel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class EloquentHardLandscapeRepository implements HardLandscapeRepository
{
    public function findForUser(int $userId, int $eventId): ?HardLandscapeEvent
    {
        $model = HardLandscapeEventModel::query()
            ->where('user_id', $userId)
            ->find($eventId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUser(int $userId): array
    {
        return HardLandscapeEventModel::query()
            ->where('user_id', $userId)
            ->orderBy('start_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function listForUserOnDate(int $userId, CarbonImmutable $date): array
    {
        return HardLandscapeEventModel::query()
            ->where('user_id', $userId)
            ->where('start_at', '<', $date->endOfDay())
            ->where('end_at', '>', $date->startOfDay())
            ->orderBy('start_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function listForUserInRange(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return HardLandscapeEventModel::query()
            ->where('user_id', $userId)
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from)
            ->orderBy('start_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function create(HardLandscapeEvent $event): HardLandscapeEvent
    {
        if ($this->overlaps($event)) {
            throw new HardLandscapeConflict;
        }

        $model = HardLandscapeEventModel::query()->create([
            'user_id' => $event->userId,
            'title' => $event->title,
            'type' => $event->type->value,
            'start_at' => $event->startAt,
            'end_at' => $event->endAt,
            'recurrence' => $event->recurrence,
        ]);

        return $this->toDomain($model);
    }

    public function update(HardLandscapeEvent $event): HardLandscapeEvent
    {
        $model = HardLandscapeEventModel::query()
            ->where('user_id', $event->userId)
            ->find($event->id);

        if ($model === null) {
            throw new InvalidArgumentException('Hard Landscape event not found.');
        }

        if ($this->overlaps($event, $model->id)) {
            throw new HardLandscapeConflict;
        }

        $model->update([
            'title' => $event->title,
            'type' => $event->type->value,
            'start_at' => $event->startAt,
            'end_at' => $event->endAt,
            'recurrence' => $event->recurrence,
        ]);

        $model->refresh();

        return $this->toDomain($model);
    }

    public function deleteForUser(int $userId, int $eventId): void
    {
        $model = HardLandscapeEventModel::query()
            ->where('user_id', $userId)
            ->find($eventId);

        if ($model === null) {
            throw new InvalidArgumentException('Hard Landscape event not found.');
        }

        $model->delete();
    }

    private function overlaps(HardLandscapeEvent $event, ?int $excludeId = null): bool
    {
        $query = HardLandscapeEventModel::query()
            ->where('user_id', $event->userId)
            ->where('start_at', '<', $event->endAt)
            ->where('end_at', '>', $event->startAt);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function toDomain(HardLandscapeEventModel $model): HardLandscapeEvent
    {
        return new HardLandscapeEvent(
            $model->id,
            $model->user_id,
            $model->title,
            new HardLandscapeType($model->type),
            CarbonImmutable::parse($model->start_at),
            CarbonImmutable::parse($model->end_at),
            $model->recurrence,
            $model->created_at !== null ? CarbonImmutable::parse($model->created_at) : null,
            $model->updated_at !== null ? CarbonImmutable::parse($model->updated_at) : null,
        );
    }
}
