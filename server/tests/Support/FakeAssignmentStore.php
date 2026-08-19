<?php

namespace Tests\Support;

use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleAssignmentOverlap;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * In-memory repository double that mirrors the persistence contract for
 * pure unit tests of apply-use-case orchestration (no DB).
 */
final class FakeAssignmentStore implements ScheduleAssignmentRepository
{
    /** @var array<int, ScheduleAssignment> */
    public array $rows = [];

    /** @var array<int, int> taskId → owning userId */
    public array $tasks = [];

    public function findForUser(int $userId, int $assignmentId): ?ScheduleAssignment
    {
        foreach ($this->rows as $row) {
            if ($row->userId === $userId && $row->id === $assignmentId) {
                return $row;
            }
        }

        return null;
    }

    public function currentScheduleVersion(int $userId): ScheduleVersion
    {
        $max = 0;
        foreach ($this->rows as $row) {
            if ($row->userId === $userId) {
                $max = max($max, $row->scheduleVersion);
            }
        }

        return new ScheduleVersion($max === 0 ? 1 : $max);
    }

    public function listForUserOnDate(int $userId, CarbonImmutable $date): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (ScheduleAssignment $row) => $row->userId === $userId
                && $row->date->toDateString() === $date->toDateString(),
        ));
    }

    public function listForUserInRange(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (ScheduleAssignment $row) => $row->userId === $userId
                && $row->startAt->lessThan($to)
                && $row->endAt->greaterThan($from),
        ));
    }

    public function listForTask(int $taskId): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (ScheduleAssignment $row) => $row->taskId === $taskId,
        ));
    }

    public function listForUserAtVersion(int $userId, ScheduleVersion $version): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (ScheduleAssignment $row) => $row->userId === $userId
                && $row->scheduleVersion === $version->value,
        ));
    }

    public function create(ScheduleAssignment $assignment): ScheduleAssignment
    {
        if (! isset($this->tasks[$assignment->taskId])
            || $this->tasks[$assignment->taskId] !== $assignment->userId) {
            throw new InvalidArgumentException('Task not found or does not belong to user.');
        }

        foreach ($this->rows as $row) {
            if ($row->userId === $assignment->userId && $row->timeRange()->overlaps($assignment->timeRange())) {
                throw new ScheduleAssignmentOverlap;
            }
        }

        $this->rows[] = $assignment;

        return $assignment;
    }

    public function update(ScheduleAssignment $assignment, int $baseVersion): ScheduleAssignment
    {
        throw new \LogicException('Not exercised by these tests.');
    }

    public function deleteForUser(int $userId, int $assignmentId): void
    {
        foreach ($this->rows as $i => $row) {
            if ($row->userId === $userId && $row->id === $assignmentId) {
                unset($this->rows[$i]);
            }
        }
    }

    public function seed(ScheduleAssignment $assignment): void
    {
        $this->rows[] = $assignment->withId(count($this->rows) + 1);
        $this->tasks[$assignment->taskId] = $assignment->userId;
    }
}
