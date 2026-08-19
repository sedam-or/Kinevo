<?php

namespace App\Infrastructure\Focus;

use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Focus\FocusSession;
use App\Models\FocusSession as FocusSessionModel;
use Carbon\CarbonImmutable;

final class EloquentFocusSessionRepository implements FocusSessionRepository
{
    public function create(FocusSession $session): FocusSession
    {
        $model = FocusSessionModel::query()->create([
            'user_id' => $session->userId,
            'task_id' => $session->taskId,
            'started_at' => $session->startedAt,
            'ended_at' => $session->endedAt,
            'duration_minutes' => $session->durationMinutes,
        ]);

        return $this->toDomain($model);
    }

    public function listForUser(int $userId, ?int $taskId = null, int $limit = 50): array
    {
        $query = FocusSessionModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        if ($taskId !== null) {
            $query->where('task_id', $taskId);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function listSince(int $userId, CarbonImmutable $since, int $limit = 200): array
    {
        return FocusSessionModel::query()
            ->where('user_id', $userId)
            ->where('started_at', '>=', $since)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    private function toDomain(FocusSessionModel $model): FocusSession
    {
        return new FocusSession(
            $model->id,
            $model->user_id,
            $model->task_id,
            CarbonImmutable::parse($model->started_at),
            CarbonImmutable::parse($model->ended_at),
            $model->duration_minutes,
        );
    }
}
