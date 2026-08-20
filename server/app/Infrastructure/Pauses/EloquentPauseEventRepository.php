<?php

namespace App\Infrastructure\Pauses;

use App\Domain\Pauses\Contracts\PauseEventRepository;
use App\Domain\Pauses\PauseEvent;
use App\Domain\Pauses\ValueObjects\PauseEventType;
use App\Models\PauseEvent as PauseEventModel;
use Carbon\CarbonImmutable;

final class EloquentPauseEventRepository implements PauseEventRepository
{
    public function create(PauseEvent $event): PauseEvent
    {
        $model = PauseEventModel::query()->create([
            'user_id' => $event->userId,
            'type' => $event->type->value,
            'week_start' => $event->weekStart->toDateString(),
            'week_end' => $event->weekEnd->toDateString(),
            'keep_task_ids' => $event->keepTaskIds,
            'moved_task_ids' => $event->movedTaskIds,
            'conflict_task_ids' => $event->conflictTaskIds,
            'schedule_version' => $event->scheduleVersion,
        ]);

        return $this->toDomain($model);
    }

    public function findForUser(int $userId, int $eventId): ?PauseEvent
    {
        $model = PauseEventModel::query()
            ->where('user_id', $userId)
            ->find($eventId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findEmergencyForWeek(int $userId, CarbonImmutable $date): ?PauseEvent
    {
        $model = PauseEventModel::query()
            ->where('user_id', $userId)
            ->where('type', PauseEventType::EMERGENCY)
            ->whereDate('week_start', '<=', $date->toDateString())
            ->whereDate('week_end', '>=', $date->toDateString())
            ->orderByDesc('id')
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function isWeekExceptional(int $userId, CarbonImmutable $date): bool
    {
        return $this->findEmergencyForWeek($userId, $date) !== null;
    }

    public function listForUser(int $userId, int $limit = 50): array
    {
        return PauseEventModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('week_start')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    private function toDomain(PauseEventModel $model): PauseEvent
    {
        return new PauseEvent(
            $model->id,
            $model->user_id,
            new PauseEventType($model->type),
            CarbonImmutable::parse($model->week_start),
            CarbonImmutable::parse($model->week_end),
            $model->keep_task_ids ?? [],
            $model->moved_task_ids ?? [],
            $model->conflict_task_ids ?? [],
            $model->schedule_version,
            $model->created_at !== null ? CarbonImmutable::parse($model->created_at) : null,
        );
    }
}
