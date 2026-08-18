<?php

namespace App\Infrastructure\Tasks;

use App\Domain\Tasks\Contracts\SubtaskRepository;
use App\Domain\Tasks\Subtask;
use App\Models\Subtask as SubtaskModel;

final class EloquentSubtaskRepository implements SubtaskRepository
{
    public function findForUser(int $userId, int $subtaskId): ?Subtask
    {
        $model = SubtaskModel::query()->where('user_id', $userId)->find($subtaskId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForTask(int $userId, int $taskId): array
    {
        return SubtaskModel::query()
            ->where('user_id', $userId)
            ->where('task_id', $taskId)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function create(int $userId, Subtask $subtask): Subtask
    {
        $model = SubtaskModel::query()->create([
            'user_id' => $userId,
            ...array_diff_key($subtask->toArray(), array_flip(['id', 'user_id'])),
        ]);

        return $this->toDomain($model);
    }

    public function update(Subtask $subtask): Subtask
    {
        $model = SubtaskModel::query()->findOrFail($subtask->id);
        $model->update(array_diff_key($subtask->toArray(), array_flip(['id', 'user_id'])));
        $model->refresh();

        return $this->toDomain($model);
    }

    public function delete(int $userId, int $subtaskId): void
    {
        SubtaskModel::query()->where('user_id', $userId)->where('id', $subtaskId)->delete();
    }

    private function toDomain(SubtaskModel $model): Subtask
    {
        return new Subtask(
            $model->id,
            $model->user_id,
            $model->task_id,
            $model->title,
            $model->notes,
            $model->sequence,
            (bool) $model->completed,
            $model->version,
        );
    }
}
