<?php

namespace App\Infrastructure\Observability;

use App\Domain\Observability\Contracts\SchedulerRunRepository;
use App\Domain\Observability\SchedulerRun;
use App\Models\SchedulerRun as SchedulerRunModel;
use Carbon\CarbonImmutable;

final readonly class EloquentSchedulerRunRepository implements SchedulerRunRepository
{
    public function record(SchedulerRun $run): SchedulerRun
    {
        $model = SchedulerRunModel::query()->create([
            'user_id' => $run->userId,
            'job' => $run->job,
            'status' => $run->status,
            'duration_ms' => $run->durationMs,
            'error' => $run->error,
            'started_at' => $run->startedAt,
        ]);

        return $run->withId($model->id);
    }

    public function listRecent(int $limit = 20): array
    {
        return SchedulerRunModel::query()
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    private function toDomain(SchedulerRunModel $model): SchedulerRun
    {
        return new SchedulerRun(
            $model->id,
            $model->user_id,
            $model->job,
            $model->status,
            $model->duration_ms,
            $model->error,
            CarbonImmutable::parse($model->started_at),
        );
    }
}
