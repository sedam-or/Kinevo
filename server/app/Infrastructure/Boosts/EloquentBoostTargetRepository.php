<?php

namespace App\Infrastructure\Boosts;

use App\Domain\Boosts\BoostTarget;
use App\Domain\Boosts\Contracts\BoostTargetRepository;
use App\Domain\Boosts\ValueObjects\BoostTargetStatus;
use App\Models\BoostTarget as BoostTargetModel;
use Carbon\CarbonImmutable;

final class EloquentBoostTargetRepository implements BoostTargetRepository
{
    public function create(BoostTarget $target): BoostTarget
    {
        $model = BoostTargetModel::query()->create([
            'user_id' => $target->userId,
            'break_period_id' => $target->breakPeriodId,
            'start_date' => $target->startDate->toDateString(),
            'end_date' => $target->endDate->toDateString(),
            'target_percent' => $target->targetPercent,
            'status' => $target->status->value,
        ]);

        return $this->toDomain($model);
    }

    public function findForUser(int $userId, int $targetId): ?BoostTarget
    {
        $model = BoostTargetModel::query()
            ->where('user_id', $userId)
            ->find($targetId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findActiveForUser(int $userId): ?BoostTarget
    {
        $model = BoostTargetModel::query()
            ->where('user_id', $userId)
            ->where('status', BoostTargetStatus::ACTIVE)
            ->orderByDesc('id')
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function findActiveOn(int $userId, CarbonImmutable $date): ?BoostTarget
    {
        $model = BoostTargetModel::query()
            ->where('user_id', $userId)
            ->where('status', BoostTargetStatus::ACTIVE)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->orderByDesc('id')
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function end(BoostTarget $target): ?BoostTarget
    {
        $model = BoostTargetModel::query()
            ->where('user_id', $target->userId)
            ->where('id', $target->id)
            ->where('status', BoostTargetStatus::ACTIVE)
            ->first();

        if ($model === null) {
            return null;
        }

        $model->update(['status' => BoostTargetStatus::ENDED]);

        return $this->toDomain($model->fresh());
    }

    public function listForUser(int $userId, int $limit = 50): array
    {
        return BoostTargetModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    private function toDomain(BoostTargetModel $model): BoostTarget
    {
        return new BoostTarget(
            $model->id,
            $model->user_id,
            $model->break_period_id,
            CarbonImmutable::parse($model->start_date),
            CarbonImmutable::parse($model->end_date),
            $model->target_percent,
            new BoostTargetStatus($model->status),
            $model->created_at !== null ? CarbonImmutable::parse($model->created_at) : null,
        );
    }
}
