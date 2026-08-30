<?php

namespace App\Application\Scheduling;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentStatus;
use InvalidArgumentException;

/**
 * Lock/unlock a scheduled task's placement (ADR-015 locked-task contract).
 *
 * A locked assignment is a USER-FIXED placement: the scheduler and the
 * rescheduler must never move it, and only the user can unlock it. The
 * `locked` flag is the existing persisted column; locking is idempotent and
 * bumping the assignment's optimistic version follows the repository's
 * stale-version 409 contract. AI has no path to this use case.
 */
final readonly class SetAssignmentLockUseCase
{
    public function __construct(
        private ScheduleAssignmentRepository $assignments,
        private RecordActivityUseCase $recordActivity,
    ) {}

    public function __invoke(int $userId, int $taskId, bool $locked, ?int $baseVersion = null): ScheduleAssignment
    {
        $assignment = $this->activeAssignmentForTask($userId, $taskId);

        if ($assignment === null) {
            throw new InvalidArgumentException('No scheduled placement exists for this task.');
        }

        // Idempotent: locking a locked placement (and vice versa) is a no-op.
        if ($assignment->locked === $locked) {
            return $assignment;
        }

        $updated = $assignment->withLocked($locked);

        // Stale base version → ScheduleAssignmentVersionConflict (409).
        $saved = $this->assignments->update($updated, $baseVersion ?? $assignment->version);

        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            $locked ? ActivityEventType::assignmentLocked() : ActivityEventType::assignmentUnlocked(),
            'task_assignment',
            $saved->id,
            $locked ? 'Placement locked' : 'Placement unlocked',
            operationId: ($locked ? 'assignment_locked:' : 'assignment_unlocked:').$userId.':'.$saved->id.':'.$saved->version,
            payload: ['task_id' => $taskId, 'locked' => $locked],
        ));

        return $saved;
    }

    private function activeAssignmentForTask(int $userId, int $taskId): ?ScheduleAssignment
    {
        foreach ($this->assignments->listForTask($taskId) as $assignment) {
            if ($assignment->userId === $userId
                && $assignment->status->equals(ScheduleAssignmentStatus::scheduled())) {
                return $assignment;
            }
        }

        return null;
    }
}
