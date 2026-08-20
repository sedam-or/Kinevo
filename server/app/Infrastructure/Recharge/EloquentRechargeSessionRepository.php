<?php

namespace App\Infrastructure\Recharge;

use App\Domain\Recharge\Contracts\RechargeSessionRepository;
use App\Domain\Recharge\RechargeSession;
use App\Domain\Recharge\ValueObjects\RechargeStatus;
use App\Models\RechargeSession as RechargeSessionModel;
use Carbon\CarbonImmutable;

final class EloquentRechargeSessionRepository implements RechargeSessionRepository
{
    public function create(RechargeSession $session): RechargeSession
    {
        $model = RechargeSessionModel::query()->create([
            'user_id' => $session->userId,
            'status' => $session->status->value,
            'started_at' => $session->startedAt,
            'last_resumed_at' => $session->lastResumedAt,
            'accumulated_seconds' => $session->accumulatedSeconds,
            'duration_minutes' => $session->durationMinutes,
            'ended_at' => $session->endedAt,
        ]);

        return $this->toDomain($model);
    }

    public function update(RechargeSession $session): RechargeSession
    {
        $model = RechargeSessionModel::query()->findOrFail($session->id);
        $model->update([
            'status' => $session->status->value,
            'last_resumed_at' => $session->lastResumedAt,
            'accumulated_seconds' => $session->accumulatedSeconds,
            'duration_minutes' => $session->durationMinutes,
            'ended_at' => $session->endedAt,
        ]);
        $model->refresh();

        return $this->toDomain($model);
    }

    public function findForUser(int $userId, int $sessionId): ?RechargeSession
    {
        $model = RechargeSessionModel::query()
            ->where('user_id', $userId)
            ->find($sessionId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findActiveForUser(int $userId): ?RechargeSession
    {
        $model = RechargeSessionModel::query()
            ->where('user_id', $userId)
            ->whereIn('status', [RechargeStatus::RUNNING, RechargeStatus::PAUSED])
            ->orderByDesc('id')
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUser(int $userId, int $limit = 50): array
    {
        return RechargeSessionModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function sumCompletedMinutesBetween(int $userId, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) RechargeSessionModel::query()
            ->where('user_id', $userId)
            ->where('status', RechargeStatus::COMPLETED)
            ->whereBetween('ended_at', [$start, $end])
            ->sum('duration_minutes');
    }

    public function countCompletedBetween(int $userId, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return RechargeSessionModel::query()
            ->where('user_id', $userId)
            ->where('status', RechargeStatus::COMPLETED)
            ->whereBetween('ended_at', [$start, $end])
            ->count();
    }

    private function toDomain(RechargeSessionModel $model): RechargeSession
    {
        return new RechargeSession(
            $model->id,
            $model->user_id,
            new RechargeStatus($model->status),
            CarbonImmutable::parse($model->started_at),
            $model->last_resumed_at !== null ? CarbonImmutable::parse($model->last_resumed_at) : null,
            $model->accumulated_seconds,
            $model->duration_minutes,
            $model->ended_at !== null ? CarbonImmutable::parse($model->ended_at) : null,
        );
    }
}
