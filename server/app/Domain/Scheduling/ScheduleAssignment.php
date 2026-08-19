<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentStatus;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Persistent aggregate representing an actual scheduled placement of a Task
 * (FR-01/FR-02/FR-08). Bridges the in-memory scheduling engines to the
 * canonical `task_assignments` store.
 */
final class ScheduleAssignment
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $taskId,
        public readonly CarbonImmutable $date,
        public readonly CarbonImmutable $startAt,
        public readonly CarbonImmutable $endAt,
        public readonly int $durationMinutes,
        public readonly ScheduleAssignmentStatus $status,
        public readonly ScheduleAssignmentSource $source,
        public readonly int $scheduleVersion,
        public readonly bool $locked,
        public readonly int $version,
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?CarbonImmutable $updatedAt = null,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('Assignment user_id must be positive.');
        }

        if ($this->taskId <= 0) {
            throw new InvalidArgumentException('Assignment task_id must be positive.');
        }

        if ($this->durationMinutes <= 0) {
            throw new InvalidArgumentException('Assignment duration_minutes must be positive.');
        }

        if ($this->scheduleVersion <= 0) {
            throw new InvalidArgumentException('Assignment schedule_version must be positive.');
        }
    }

    public static function create(
        int $userId,
        int $taskId,
        CarbonImmutable|string $date,
        CarbonImmutable|string $startAt,
        CarbonImmutable|string $endAt,
        ?int $durationMinutes = null,
        ?ScheduleAssignmentStatus $status = null,
        ?ScheduleAssignmentSource $source = null,
        int $scheduleVersion = 1,
        bool $locked = false,
    ): self {
        $dateInstance = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);
        $startInstance = $startAt instanceof CarbonImmutable ? $startAt : CarbonImmutable::parse($startAt);
        $endInstance = $endAt instanceof CarbonImmutable ? $endAt : CarbonImmutable::parse($endAt);

        $range = new TimeRange($startInstance, $endInstance);
        $derivedDuration = $range->durationMinutes()->value();

        if ($durationMinutes !== null && $durationMinutes !== $derivedDuration) {
            throw new InvalidArgumentException(
                "Assignment duration_minutes ({$durationMinutes}) does not match start/end diff ({$derivedDuration})."
            );
        }

        if ($dateInstance->toDateString() !== $startInstance->toDateString()) {
            throw new InvalidArgumentException(
                'Assignment date must match the calendar date of start_at.'
            );
        }

        return new self(
            0,
            $userId,
            $taskId,
            $dateInstance,
            $startInstance,
            $endInstance,
            $derivedDuration,
            $status ?? ScheduleAssignmentStatus::scheduled(),
            $source ?? ScheduleAssignmentSource::manual(),
            $scheduleVersion,
            $locked,
            1,
        );
    }

    public function withId(int $id): self
    {
        return $this->reborn(['id' => $id]);
    }

    public function withLocked(bool $locked): self
    {
        if ($this->locked === $locked) {
            return $this;
        }

        return $this->reborn([
            'locked' => $locked,
            'version' => $this->version + 1,
        ]);
    }

    public function withStatus(ScheduleAssignmentStatus $status): self
    {
        if ($this->status->equals($status)) {
            return $this;
        }

        return $this->reborn([
            'status' => $status,
            'version' => $this->version + 1,
        ]);
    }

    public function withSource(ScheduleAssignmentSource $source): self
    {
        if ($this->source->equals($source)) {
            return $this;
        }

        return $this->reborn([
            'source' => $source,
            'version' => $this->version + 1,
        ]);
    }

    public function withScheduleVersion(int $scheduleVersion): self
    {
        if ($scheduleVersion <= 0) {
            throw new InvalidArgumentException('schedule_version must be positive.');
        }

        if ($this->scheduleVersion === $scheduleVersion) {
            return $this;
        }

        return $this->reborn([
            'scheduleVersion' => $scheduleVersion,
            'version' => $this->version + 1,
        ]);
    }

    public function withTimeRange(
        CarbonImmutable|string $date,
        CarbonImmutable|string $startAt,
        CarbonImmutable|string $endAt,
    ): self {
        $dateInstance = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);
        $startInstance = $startAt instanceof CarbonImmutable ? $startAt : CarbonImmutable::parse($startAt);
        $endInstance = $endAt instanceof CarbonImmutable ? $endAt : CarbonImmutable::parse($endAt);

        $range = new TimeRange($startInstance, $endInstance);

        if ($dateInstance->toDateString() !== $startInstance->toDateString()) {
            throw new InvalidArgumentException(
                'Assignment date must match the calendar date of start_at.'
            );
        }

        return $this->reborn([
            'date' => $dateInstance,
            'startAt' => $startInstance,
            'endAt' => $endInstance,
            'durationMinutes' => $range->durationMinutes()->value(),
            'version' => $this->version + 1,
        ]);
    }

    public function complete(): self
    {
        return $this->withStatus(ScheduleAssignmentStatus::completed());
    }

    public function cancel(): self
    {
        return $this->withStatus(ScheduleAssignmentStatus::cancelled());
    }

    public function timeRange(): TimeRange
    {
        return new TimeRange($this->startAt, $this->endAt);
    }

    public function overlapsWith(self $other): bool
    {
        return $this->timeRange()->overlaps($other->timeRange());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'task_id' => $this->taskId,
            'date' => $this->date->toDateString(),
            'start_at' => $this->startAt->toISOString(),
            'end_at' => $this->endAt->toISOString(),
            'duration_minutes' => $this->durationMinutes,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'schedule_version' => $this->scheduleVersion,
            'locked' => $this->locked,
            'version' => $this->version,
            'created_at' => $this->createdAt?->toISOString(),
            'updated_at' => $this->updatedAt?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function reborn(array $props): self
    {
        $merged = array_merge([
            'id' => $this->id,
            'userId' => $this->userId,
            'taskId' => $this->taskId,
            'date' => $this->date,
            'startAt' => $this->startAt,
            'endAt' => $this->endAt,
            'durationMinutes' => $this->durationMinutes,
            'status' => $this->status,
            'source' => $this->source,
            'scheduleVersion' => $this->scheduleVersion,
            'locked' => $this->locked,
            'version' => $this->version,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ], $props);

        return new self(
            $merged['id'],
            $merged['userId'],
            $merged['taskId'],
            $merged['date'],
            $merged['startAt'],
            $merged['endAt'],
            $merged['durationMinutes'],
            $merged['status'],
            $merged['source'],
            $merged['scheduleVersion'],
            $merged['locked'],
            $merged['version'],
            $merged['createdAt'],
            $merged['updatedAt'],
        );
    }
}
