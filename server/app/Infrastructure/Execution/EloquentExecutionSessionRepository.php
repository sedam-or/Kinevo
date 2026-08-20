<?php

namespace App\Infrastructure\Execution;

use App\Domain\Execution\Contracts\ExecutionSessionRepository;
use App\Domain\Execution\ExecutionSession;
use App\Domain\Execution\ValueObjects\ExecutionStatus;
use App\Models\ExecutionSession as ExecutionSessionModel;
use Carbon\CarbonImmutable;

final class EloquentExecutionSessionRepository implements ExecutionSessionRepository
{
    public function create(ExecutionSession $session): ExecutionSession
    {
        $model = ExecutionSessionModel::query()->create([
            'user_id' => $session->userId,
            'task_id' => $session->taskId,
            'status' => $session->status->value,
            'started_at' => $session->startedAt,
            'last_resumed_at' => $session->lastResumedAt,
            'accumulated_seconds' => $session->accumulatedSeconds,
            'ended_at' => $session->endedAt,
        ]);

        return $this->toDomain($model);
    }

    public function update(ExecutionSession $session): ExecutionSession
    {
        $model = ExecutionSessionModel::query()->findOrFail($session->id);
        $model->update([
            'status' => $session->status->value,
            'last_resumed_at' => $session->lastResumedAt,
            'accumulated_seconds' => $session->accumulatedSeconds,
            'ended_at' => $session->endedAt,
        ]);
        $model->refresh();

        return $this->toDomain($model);
    }

    public function findForUser(int $userId, int $sessionId): ?ExecutionSession
    {
        $model = ExecutionSessionModel::query()
            ->where('user_id', $userId)
            ->find($sessionId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findActiveForUser(int $userId): ?ExecutionSession
    {
        $model = ExecutionSessionModel::query()
            ->where('user_id', $userId)
            ->whereIn('status', [ExecutionStatus::RUNNING, ExecutionStatus::PAUSED])
            ->orderByDesc('id')
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUser(int $userId, ?int $taskId = null, int $limit = 50): array
    {
        $query = ExecutionSessionModel::query()
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

    private function toDomain(ExecutionSessionModel $model): ExecutionSession
    {
        return new ExecutionSession(
            $model->id,
            $model->user_id,
            $model->task_id,
            new ExecutionStatus($model->status),
            CarbonImmutable::parse($model->started_at),
            $model->last_resumed_at !== null ? CarbonImmutable::parse($model->last_resumed_at) : null,
            $model->accumulated_seconds,
            $model->ended_at !== null ? CarbonImmutable::parse($model->ended_at) : null,
        );
    }
}
