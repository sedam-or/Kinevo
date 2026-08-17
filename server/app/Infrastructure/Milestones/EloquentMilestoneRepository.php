<?php

namespace App\Infrastructure\Milestones;

use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Milestones\Milestone;
use App\Domain\Milestones\ValueObjects\MilestoneStatus;
use App\Models\Milestone as MilestoneModel;
use Carbon\CarbonImmutable;

final class EloquentMilestoneRepository implements MilestoneRepository
{
    public function findForUser(int $userId, int $milestoneId): ?Milestone
    {
        $model = MilestoneModel::query()->where('user_id', $userId)->find($milestoneId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForGoal(int $userId, int $goalId): array
    {
        return MilestoneModel::query()
            ->where('user_id', $userId)
            ->where('goal_id', $goalId)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function create(int $userId, Milestone $milestone): Milestone
    {
        $model = MilestoneModel::query()->create([
            'user_id' => $userId,
            ...array_diff_key($milestone->toArray(), array_flip(['id', 'user_id'])),
        ]);

        return $this->toDomain($model);
    }

    public function update(Milestone $milestone): Milestone
    {
        $model = MilestoneModel::query()->findOrFail($milestone->id);
        $model->update(array_diff_key($milestone->toArray(), array_flip(['id', 'user_id'])));
        $model->refresh();

        return $this->toDomain($model);
    }

    public function reorder(int $userId, int $goalId, array $orderedIds): void
    {
        $existing = MilestoneModel::query()
            ->where('user_id', $userId)
            ->where('goal_id', $goalId)
            ->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => true])
            ->all();

        foreach (array_values($orderedIds) as $index => $id) {
            if (! isset($existing[$id])) {
                continue;
            }

            MilestoneModel::query()
                ->where('id', $id)
                ->update(['sequence' => $index]);
        }
    }

    private function toDomain(MilestoneModel $model): Milestone
    {
        return new Milestone(
            $model->id,
            $model->goal_id,
            $model->user_id,
            $model->title,
            $model->description,
            $model->sequence,
            $model->target_date !== null ? CarbonImmutable::parse($model->target_date) : null,
            $model->estimated_minutes,
            new MilestoneStatus($model->status),
            $model->progress_mode,
            $model->progress,
            $model->completed_at !== null ? CarbonImmutable::parse($model->completed_at) : null,
            $model->version,
        );
    }
}
