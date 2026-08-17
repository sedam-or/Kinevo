<?php

namespace App\Infrastructure\Programs;

use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Programs\Program;
use App\Domain\Programs\ValueObjects\ProgramStatus;
use App\Domain\Programs\ValueObjects\ProgramWorkloadType;
use App\Models\Program as ProgramModel;

final class EloquentProgramRepository implements ProgramRepository
{
    public function findForUser(int $userId, int $programId): ?Program
    {
        $model = ProgramModel::query()->where('user_id', $userId)->find($programId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUser(int $userId): array
    {
        return ProgramModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function create(int $userId, Program $program): Program
    {
        $model = ProgramModel::query()->create([
            'user_id' => $userId,
            ...array_diff_key($program->toArray(), array_flip(['id', 'user_id'])),
        ]);

        return $this->toDomain($model);
    }

    public function update(Program $program): Program
    {
        $model = ProgramModel::query()->findOrFail($program->id);
        $model->update(array_diff_key($program->toArray(), array_flip(['id', 'user_id'])));
        $model->refresh();

        return $this->toDomain($model);
    }

    private function toDomain(ProgramModel $model): Program
    {
        return new Program(
            $model->id,
            $model->user_id,
            $model->name,
            $model->description,
            $model->category,
            new ProgramWorkloadType($model->workload_type),
            $model->weekly_target_minutes,
            $model->min_weekly_minutes,
            $model->max_weekly_minutes,
            new ProgramStatus($model->status),
            $model->priority_tier,
            $model->version,
        );
    }
}
