<?php

namespace App\Infrastructure\Tasks;

use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\ValueObjects\TaskStatus;
use App\Models\Task as TaskModel;
use Carbon\CarbonImmutable;

final class EloquentTaskRepository implements TaskRepository
{
    public function findForUser(int $userId, int $taskId): ?Task
    {
        $model = TaskModel::query()->where('user_id', $userId)->find($taskId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUser(int $userId): array
    {
        return TaskModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function create(int $userId, Task $task): Task
    {
        $model = TaskModel::query()->create([
            'user_id' => $userId,
            ...array_diff_key($task->toArray(), array_flip(['id', 'user_id'])),
        ]);

        return $this->toDomain($model);
    }

    public function update(Task $task): Task
    {
        $model = TaskModel::query()->findOrFail($task->id);
        $model->update(array_diff_key($task->toArray(), array_flip(['id', 'user_id'])));
        $model->refresh();

        return $this->toDomain($model);
    }

    private function toDomain(TaskModel $model): Task
    {
        return new Task(
            $model->id,
            $model->user_id,
            $model->program_id,
            $model->goal_id,
            $model->milestone_id,
            $model->title,
            $model->description,
            new TaskStatus($model->status),
            $model->priority_tier,
            $model->estimated_minutes,
            $model->due_at !== null ? CarbonImmutable::parse($model->due_at) : null,
            $model->progress_mode ?? 'derived',
            $model->progress,
            $model->version,
        );
    }
}
