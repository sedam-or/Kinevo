<?php

namespace App\Infrastructure\Goals;

use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Goals\Goal;
use App\Domain\Goals\ValueObjects\GoalHorizon;
use App\Domain\Goals\ValueObjects\GoalStatus;
use App\Models\Goal as GoalModel;
use Carbon\CarbonImmutable;

final class EloquentGoalRepository implements GoalRepository
{
    public function findForUser(int $userId, int $goalId): ?Goal
    {
        $model = GoalModel::query()->where('user_id', $userId)->find($goalId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUser(int $userId): array
    {
        return GoalModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function listForUserInWorkspace(int $userId, int $workspaceId): array
    {
        return GoalModel::query()
            ->where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function create(int $userId, Goal $goal): Goal
    {
        $model = GoalModel::query()->create([
            'user_id' => $userId,
            ...array_diff_key($goal->toArray(), array_flip(['id', 'user_id'])),
        ]);

        return $this->toDomain($model);
    }

    public function update(Goal $goal): Goal
    {
        $model = GoalModel::query()->findOrFail($goal->id);
        $model->update(array_diff_key($goal->toArray(), array_flip(['id', 'user_id'])));
        $model->refresh();

        return $this->toDomain($model);
    }

    public function countActiveForHorizon(int $userId, GoalHorizon $horizon): int
    {
        return GoalModel::query()
            ->where('user_id', $userId)
            ->where('horizon', $horizon->value)
            ->whereNotIn('status', [GoalStatus::COMPLETED, GoalStatus::ARCHIVED, GoalStatus::DROPPED])
            ->count();
    }

    private function toDomain(GoalModel $model): Goal
    {
        return new Goal(
            $model->id,
            $model->user_id,
            $model->title,
            $model->description,
            new GoalHorizon($model->horizon),
            $model->start_date !== null ? CarbonImmutable::parse($model->start_date) : null,
            $model->target_date !== null ? CarbonImmutable::parse($model->target_date) : null,
            $model->target_metric,
            new GoalStatus($model->status),
            $model->priority_tier,
            $model->progress_mode,
            $model->progress,
            $model->workspace_id,
        );
    }
}
