<?php

namespace App\Infrastructure\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleAssignmentOverlap;
use App\Domain\Scheduling\ScheduleAssignmentVersionConflict;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentStatus;
use App\Models\Task;
use App\Models\TaskAssignment as TaskAssignmentModel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class EloquentScheduleAssignmentRepository implements ScheduleAssignmentRepository
{
    public function findForUser(int $userId, int $assignmentId): ?ScheduleAssignment
    {
        $model = TaskAssignmentModel::query()
            ->where('user_id', $userId)
            ->find($assignmentId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUserOnDate(int $userId, CarbonImmutable $date): array
    {
        return TaskAssignmentModel::query()
            ->where('user_id', $userId)
            ->whereDate('date', $date->toDateString())
            ->orderBy('start_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function listForUserInRange(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return TaskAssignmentModel::query()
            ->where('user_id', $userId)
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from)
            ->orderBy('start_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function listForTask(int $taskId): array
    {
        return TaskAssignmentModel::query()
            ->where('task_id', $taskId)
            ->orderBy('start_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function create(ScheduleAssignment $assignment): ScheduleAssignment
    {
        $this->assertTaskOwnedByUser($assignment->userId, $assignment->taskId);

        if ($this->overlaps($assignment)) {
            throw new ScheduleAssignmentOverlap;
        }

        $model = TaskAssignmentModel::query()->create([
            'user_id' => $assignment->userId,
            'task_id' => $assignment->taskId,
            'date' => $assignment->date->toDateString(),
            'start_at' => $assignment->startAt,
            'end_at' => $assignment->endAt,
            'duration_minutes' => $assignment->durationMinutes,
            'status' => $assignment->status->value,
            'source' => $assignment->source->value,
            'schedule_version' => $assignment->scheduleVersion,
            'locked' => $assignment->locked,
            'version' => $assignment->version,
        ]);

        return $this->toDomain($model);
    }

    public function update(ScheduleAssignment $assignment, int $baseVersion): ScheduleAssignment
    {
        $model = TaskAssignmentModel::query()
            ->where('user_id', $assignment->userId)
            ->where('id', $assignment->id)
            ->where('version', $baseVersion)
            ->first();

        if ($model === null) {
            $current = TaskAssignmentModel::query()
                ->where('user_id', $assignment->userId)
                ->find($assignment->id);
            $actualVersion = $current !== null ? $current->version : 0;

            throw new ScheduleAssignmentVersionConflict($baseVersion, $actualVersion);
        }

        $this->assertTaskOwnedByUser($assignment->userId, $assignment->taskId);

        if ($this->overlaps($assignment, $model->id)) {
            throw new ScheduleAssignmentOverlap;
        }

        $model->update([
            'task_id' => $assignment->taskId,
            'date' => $assignment->date->toDateString(),
            'start_at' => $assignment->startAt,
            'end_at' => $assignment->endAt,
            'duration_minutes' => $assignment->durationMinutes,
            'status' => $assignment->status->value,
            'source' => $assignment->source->value,
            'schedule_version' => $assignment->scheduleVersion,
            'locked' => $assignment->locked,
            'version' => $assignment->version,
        ]);

        $model->refresh();

        return $this->toDomain($model);
    }

    public function deleteForUser(int $userId, int $assignmentId): void
    {
        $model = TaskAssignmentModel::query()
            ->where('user_id', $userId)
            ->find($assignmentId);

        if ($model === null) {
            throw new InvalidArgumentException('Assignment not found.');
        }

        $model->delete();
    }

    private function assertTaskOwnedByUser(int $userId, int $taskId): void
    {
        $task = Task::query()->where('user_id', $userId)->find($taskId);

        if ($task === null) {
            throw new InvalidArgumentException('Task not found or does not belong to user.');
        }
    }

    private function overlaps(ScheduleAssignment $assignment, ?int $excludeId = null): bool
    {
        $query = TaskAssignmentModel::query()
            ->where('user_id', $assignment->userId)
            ->where('start_at', '<', $assignment->endAt)
            ->where('end_at', '>', $assignment->startAt);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function toDomain(TaskAssignmentModel $model): ScheduleAssignment
    {
        return new ScheduleAssignment(
            $model->id,
            $model->user_id,
            $model->task_id,
            CarbonImmutable::parse($model->date),
            CarbonImmutable::parse($model->start_at),
            CarbonImmutable::parse($model->end_at),
            $model->duration_minutes,
            new ScheduleAssignmentStatus($model->status),
            new ScheduleAssignmentSource($model->source),
            $model->schedule_version,
            (bool) $model->locked,
            $model->version,
            $model->created_at !== null ? CarbonImmutable::parse($model->created_at) : null,
            $model->updated_at !== null ? CarbonImmutable::parse($model->updated_at) : null,
        );
    }
}
