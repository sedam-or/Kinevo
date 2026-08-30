<?php

namespace App\Domain\Scheduling\Contracts;

use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleSupersession;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use Carbon\CarbonImmutable;

interface ScheduleAssignmentRepository
{
    public function findForUser(int $userId, int $assignmentId): ?ScheduleAssignment;

    /**
     * The user's current schedule version: the highest persisted
     * `schedule_version` (baseline version 1 when no assignments exist).
     */
    public function currentScheduleVersion(int $userId): ScheduleVersion;

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

    /**
     * @return array<int, ScheduleAssignment>
     */
    public function listForUserAtVersion(int $userId, ScheduleVersion $version): array;

    public function create(ScheduleAssignment $assignment): ScheduleAssignment;

    public function update(ScheduleAssignment $assignment, int $baseVersion): ScheduleAssignment;

    /**
     * Delete an assignment, first archiving it into
     * `schedule_assignment_history` (ADR-015 history model) within the
     * caller's transaction. Supersession metadata is optional: apply paths
     * pass the superseding schedule version; other mutation paths archive
     * with mechanism-less provenance.
     */
    public function deleteForUser(int $userId, int $assignmentId, ?ScheduleSupersession $supersededBy = null): void;

    /**
     * Placement timeline for one task (ADR-015 minimum query surface):
     * archived placements, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public function historyForTask(int $userId, int $taskId): array;
}
