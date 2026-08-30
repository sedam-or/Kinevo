<?php

namespace App\Infrastructure\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\ScheduleOverride;
use App\Domain\Scheduling\ScheduleOverrideConflict;
use App\Domain\Scheduling\ValueObjects\ScheduleOverrideType;
use App\Models\HardLandscapeEvent as HardLandscapeEventModel;
use App\Models\ScheduleOverride as ScheduleOverrideModel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class EloquentScheduleOverrideRepository implements ScheduleOverrideRepository
{
    public function findForUser(int $userId, int $overrideId): ?ScheduleOverride
    {
        $model = ScheduleOverrideModel::query()
            ->where('user_id', $userId)
            ->find($overrideId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUser(int $userId): array
    {
        return ScheduleOverrideModel::query()
            ->where('user_id', $userId)
            ->orderBy('effective_from')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function listForSource(int $userId, int $hardLandscapeEventId): array
    {
        return ScheduleOverrideModel::query()
            ->where('user_id', $userId)
            ->where('hard_landscape_event_id', $hardLandscapeEventId)
            ->orderBy('effective_from')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function create(ScheduleOverride $override): ScheduleOverride
    {
        $this->assertSourceOwnedByUser($override->userId, $override->hardLandscapeEventId);

        if ($this->overlaps($override)) {
            throw new ScheduleOverrideConflict;
        }

        $model = ScheduleOverrideModel::query()->create([
            'user_id' => $override->userId,
            'hard_landscape_event_id' => $override->hardLandscapeEventId,
            'type' => $override->type->value,
            'effective_from' => $override->effectiveFrom,
            'effective_to' => $override->effectiveTo,
            'override_start_at' => $override->overrideStartAt,
            'override_end_at' => $override->overrideEndAt,
            'reason' => $override->reason,
            'cancels_occurrence' => $override->cancelsOccurrence,
        ]);

        return $this->toDomain($model);
    }

    public function update(ScheduleOverride $override): ScheduleOverride
    {
        $model = ScheduleOverrideModel::query()
            ->where('user_id', $override->userId)
            ->find($override->id);

        if ($model === null) {
            throw new InvalidArgumentException('Schedule override not found.');
        }

        $this->assertSourceOwnedByUser($override->userId, $override->hardLandscapeEventId);

        if ($this->overlaps($override, $model->id)) {
            throw new ScheduleOverrideConflict;
        }

        $model->update([
            'hard_landscape_event_id' => $override->hardLandscapeEventId,
            'type' => $override->type->value,
            'effective_from' => $override->effectiveFrom,
            'effective_to' => $override->effectiveTo,
            'override_start_at' => $override->overrideStartAt,
            'override_end_at' => $override->overrideEndAt,
            'reason' => $override->reason,
            'cancels_occurrence' => $override->cancelsOccurrence,
        ]);

        $model->refresh();

        return $this->toDomain($model);
    }

    public function deleteForUser(int $userId, int $overrideId): void
    {
        $model = ScheduleOverrideModel::query()
            ->where('user_id', $userId)
            ->find($overrideId);

        if ($model === null) {
            throw new InvalidArgumentException('Schedule override not found.');
        }

        $model->delete();
    }

    private function assertSourceOwnedByUser(int $userId, int $hardLandscapeEventId): void
    {
        $source = HardLandscapeEventModel::query()
            ->where('user_id', $userId)
            ->find($hardLandscapeEventId);

        if ($source === null) {
            throw new InvalidArgumentException('Hard Landscape event not found or does not belong to user.');
        }
    }

    private function overlaps(ScheduleOverride $override, ?int $excludeId = null): bool
    {
        $query = ScheduleOverrideModel::query()
            ->where('user_id', $override->userId)
            ->where('hard_landscape_event_id', $override->hardLandscapeEventId)
            ->where('override_start_at', '<', $override->overrideEndAt)
            ->where('override_end_at', '>', $override->overrideStartAt);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function toDomain(ScheduleOverrideModel $model): ScheduleOverride
    {
        return new ScheduleOverride(
            $model->id,
            $model->user_id,
            $model->hard_landscape_event_id,
            new ScheduleOverrideType($model->type),
            CarbonImmutable::parse($model->effective_from),
            CarbonImmutable::parse($model->effective_to),
            CarbonImmutable::parse($model->override_start_at),
            CarbonImmutable::parse($model->override_end_at),
            $model->reason,
            (bool) $model->cancels_occurrence,
            $model->created_at !== null ? CarbonImmutable::parse($model->created_at) : null,
            $model->updated_at !== null ? CarbonImmutable::parse($model->updated_at) : null,
        );
    }
}
