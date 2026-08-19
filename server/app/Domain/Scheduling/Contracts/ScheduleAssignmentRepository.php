<?php

namespace App\Domain\Scheduling\Contracts;

use App\Domain\Scheduling\ScheduleAssignment;
use Carbon\CarbonImmutable;

interface ScheduleAssignmentRepository
{
    public function findForUser(int $userId, int $assignmentId): ?ScheduleAssignment;

    /**
     * @return array<int, ScheduleAssignment>
     */
    public function listForUserOnDate(int $userId, CarbonImmutable $date): array;

    /**
     * @return array<int, ScheduleAssignment>
     */
    public function listForUserInRange(int $userId, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * @return array<int, ScheduleAssignment>
     */
    public function listForTask(int $taskId): array;

    public function create(ScheduleAssignment $assignment): ScheduleAssignment;

    public function update(ScheduleAssignment $assignment, int $baseVersion): ScheduleAssignment;

    public function deleteForUser(int $userId, int $assignmentId): void;
}
