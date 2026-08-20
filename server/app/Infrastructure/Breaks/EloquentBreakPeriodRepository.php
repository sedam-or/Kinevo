<?php

namespace App\Infrastructure\Breaks;

use App\Domain\Breaks\BreakPeriod;
use App\Domain\Breaks\Contracts\BreakPeriodRepository;
use App\Domain\Breaks\ValueObjects\BreakPeriodStatus;
use App\Models\BreakPeriod as BreakPeriodModel;
use Carbon\CarbonImmutable;

final class EloquentBreakPeriodRepository implements BreakPeriodRepository
{
    public function create(BreakPeriod $period): BreakPeriod
    {
        $model = BreakPeriodModel::query()->create([
            'user_id' => $period->userId,
            'start_date' => $period->startDate->toDateString(),
            'end_date' => $period->endDate->toDateString(),
            'status' => $period->status->value,
        ]);

        return $this->toDomain($model);
    }

    public function findForUser(int $userId, int $periodId): ?BreakPeriod
    {
        $model = BreakPeriodModel::query()
            ->where('user_id', $userId)
            ->find($periodId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findActiveForUser(int $userId): ?BreakPeriod
    {
        $model = BreakPeriodModel::query()
            ->where('user_id', $userId)
            ->where('status', BreakPeriodStatus::ACTIVE)
            ->orderByDesc('id')
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function coversDate(int $userId, CarbonImmutable $date): bool
    {
        return BreakPeriodModel::query()
            ->where('user_id', $userId)
            ->where('status', BreakPeriodStatus::ACTIVE)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->exists();
    }

    public function coversWeek(int $userId, CarbonImmutable $date): bool
    {
        $start = $date->startOfWeek()->toDateString();
        $end = $date->endOfWeek()->toDateString();

        return BreakPeriodModel::query()
            ->where('user_id', $userId)
            ->where('status', BreakPeriodStatus::ACTIVE)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();
    }

    public function end(BreakPeriod $period): ?BreakPeriod
    {
        $model = BreakPeriodModel::query()
            ->where('user_id', $period->userId)
            ->where('id', $period->id)
            ->where('status', BreakPeriodStatus::ACTIVE)
            ->first();

        if ($model === null) {
            return null;
        }

        $model->update(['status' => BreakPeriodStatus::ENDED]);

        return $this->toDomain($model->fresh());
    }

    public function listForUser(int $userId, int $limit = 50): array
    {
        return BreakPeriodModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function listActiveEndingOnOrAfter(int $userId, CarbonImmutable $date): array
    {
        return BreakPeriodModel::query()
            ->where('user_id', $userId)
            ->where('status', BreakPeriodStatus::ACTIVE)
            ->whereDate('end_date', '>=', $date->toDateString())
            ->orderBy('end_date')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    private function toDomain(BreakPeriodModel $model): BreakPeriod
    {
        return new BreakPeriod(
            $model->id,
            $model->user_id,
            CarbonImmutable::parse($model->start_date),
            CarbonImmutable::parse($model->end_date),
            new BreakPeriodStatus($model->status),
            $model->created_at !== null ? CarbonImmutable::parse($model->created_at) : null,
        );
    }
}
