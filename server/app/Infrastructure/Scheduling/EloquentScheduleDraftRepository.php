<?php

namespace App\Infrastructure\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleDraftRepository;
use App\Domain\Scheduling\ScheduleDraftRecord;
use App\Domain\Scheduling\ValueObjects\ScheduleDraftStatus;
use App\Models\ScheduleDraftModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentScheduleDraftRepository implements ScheduleDraftRepository
{
    public function findForUser(int $userId, int $draftId): ?ScheduleDraftRecord
    {
        $model = ScheduleDraftModel::query()
            ->where('user_id', $userId)
            ->find($draftId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function listPendingForUser(int $userId): array
    {
        return ScheduleDraftModel::query()
            ->where('user_id', $userId)
            ->where('status', ScheduleDraftStatus::PENDING)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ScheduleDraftModel $model) => $this->toDomain($model))
            ->all();
    }

    public function findPendingWeeklyForWeek(int $userId, CarbonImmutable $weekAnchor): ?ScheduleDraftRecord
    {
        $model = $this->weeklyForWeekQuery($userId, $weekAnchor)
            ->where('status', ScheduleDraftStatus::PENDING)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function findWeeklyForWeek(int $userId, CarbonImmutable $weekAnchor): ?ScheduleDraftRecord
    {
        $model = $this->weeklyForWeekQuery($userId, $weekAnchor)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function refreshWeekly(int $userId, int $draftId, array $payload, int $baseVersion, CarbonImmutable $horizonFrom, CarbonImmutable $horizonTo): ScheduleDraftRecord
    {
        $model = ScheduleDraftModel::query()
            ->where('user_id', $userId)
            ->where('id', $draftId)
            ->firstOrFail();

        $model->update([
            'payload' => $payload,
            'base_version' => $baseVersion,
            'horizon_from' => $horizonFrom->toDateString(),
            'horizon_to' => $horizonTo->toDateString(),
        ]);

        return $this->toDomain($model);
    }

    /**
     * @return Builder<ScheduleDraftModel>
     */
    private function weeklyForWeekQuery(int $userId, CarbonImmutable $weekAnchor): Builder
    {
        return ScheduleDraftModel::query()
            ->where('user_id', $userId)
            ->where('source', 'weekly')
            ->whereDate('generated_for_week', $weekAnchor->toDateString());
    }

    public function listPendingWeeklyForUser(int $userId): array
    {
        return ScheduleDraftModel::query()
            ->where('user_id', $userId)
            ->where('source', 'weekly')
            ->where('status', ScheduleDraftStatus::PENDING)
            ->orderBy('created_at')
            ->get()
            ->map(fn (ScheduleDraftModel $model) => $this->toDomain($model))
            ->all();
    }

    public function create(ScheduleDraftRecord $record): ScheduleDraftRecord
    {
        $model = ScheduleDraftModel::query()->create([
            'user_id' => $record->userId,
            'source' => $record->source,
            'status' => $record->status->value,
            'payload' => $record->payload,
            'base_version' => $record->baseVersion,
            'horizon_from' => $record->horizonFrom->toDateString(),
            'horizon_to' => $record->horizonTo->toDateString(),
            'generated_for_week' => $record->generatedForWeek?->toDateString(),
        ]);

        return $this->toDomain($model);
    }

    public function updateStatus(int $userId, int $draftId, ScheduleDraftStatus $status): void
    {
        ScheduleDraftModel::query()
            ->where('user_id', $userId)
            ->where('id', $draftId)
            ->update(['status' => $status->value]);
    }

    private function toDomain(ScheduleDraftModel $model): ScheduleDraftRecord
    {
        return new ScheduleDraftRecord(
            $model->id,
            $model->user_id,
            $model->source,
            new ScheduleDraftStatus($model->status),
            (array) $model->payload,
            $model->base_version,
            CarbonImmutable::parse($model->horizon_from),
            CarbonImmutable::parse($model->horizon_to),
            $model->generated_for_week !== null ? CarbonImmutable::parse($model->generated_for_week) : null,
            $model->created_at !== null ? CarbonImmutable::instance($model->created_at) : null,
        );
    }
}
