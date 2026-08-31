<?php

namespace App\Application\Scheduling;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Contracts\ScheduleReviewRepository;
use App\Domain\Scheduling\RescheduleProposal;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleAssignmentLockedConflict;
use App\Domain\Scheduling\ScheduleVersionConflict;
use App\Domain\Scheduling\TaskMove;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\ScheduleSupersession;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use Illuminate\Support\Facades\DB;

/**
 * Explicitly apply an approved dynamic reschedule proposal (FR-28; scheduler
 * mode RESCHEDULE_PROPOSAL). Persists every task movement atomically at the
 * next schedule version. Generating/previewing a proposal never mutates the
 * schedule; only this explicit apply commits it.
 *
 * - Preview is non-mutating: proposals are plain value objects; applying a
 *   stale proposal (current version differs from the base it was computed
 *   against) → ScheduleVersionConflict (HTTP 409).
 * - Re-applying an already-applied proposal is an idempotent no-op success.
 * - Locked assignments are never moved or overwritten (FR-04/FR-28); prior
 *   auto-generated placements for moved tasks are superseded.
 * - Conflicted tasks (unable to be placed) are never deleted; they stay
 *   visible as flags on the result and keep their existing placement.
 * - Any invalid move (illegal overlap, unknown task) rolls the whole
 *   transaction back — a proposal never partially persists.
 */
final readonly class ApplyRescheduleProposalUseCase
{
    public function __construct(
        private ScheduleAssignmentRepository $assignments,
        private RecordActivityUseCase $recordActivity,
        private ScheduleReviewRepository $reviews,
    ) {}

    public function __invoke(int $userId, RescheduleProposal $proposal): RescheduleApplyResult
    {
        $current = $this->assignments->currentScheduleVersion($userId);

        // Idempotent retry: the exact proposal was already applied exactly one
        // version ahead (successful earlier apply of the same proposal).
        if ($current->value === $proposal->baseVersion->value + 1
            && $this->proposalMatchesPersisted($userId, $proposal, $current)) {
            $this->reviews->markReviewed($userId, $current->value);

            return new RescheduleApplyResult(
                $current,
                [],
                applied: false,
                conflictTaskIds: $proposal->conflictTaskIds,
            );
        }

        if (! $current->equals($proposal->baseVersion)) {
            throw new ScheduleVersionConflict($proposal->baseVersion, $current);
        }

        $newVersion = $current->next();

        $created = DB::transaction(function () use ($userId, $proposal, $newVersion): array {
            $persisted = [];

            foreach ($proposal->moves as $move) {
                $persisted[] = $this->move($userId, $move, $newVersion);
            }

            return $persisted;
        });

        // ADR-016 §2.3 — an explicit apply acknowledges the review state.
        $this->reviews->markReviewed($userId, $newVersion->value);

        // ADR-015 history model: the apply is an auditable schedule mutation.
        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            ActivityEventType::scheduleRescheduleApplied(),
            'schedule',
            $newVersion->value,
            'Reschedule proposal applied',
            operationId: 'schedule_reschedule_applied:'.$userId.':'.$newVersion->value,
            payload: ['schedule_version' => $newVersion->value, 'moves' => count($created)],
        ));

        return new RescheduleApplyResult(
            $newVersion,
            $created,
            applied: true,
            conflictTaskIds: $proposal->conflictTaskIds,
        );
    }

    private function move(int $userId, TaskMove $move, ScheduleVersion $newVersion): ScheduleAssignment
    {
        $taskId = (int) $move->taskId;
        $existing = $this->existingForTask($userId, $taskId);

        $locked = $this->lockedPlacement($existing);
        if ($locked !== null) {
            if (! $locked->timeRange()->equals($move->toSlot)) {
                throw new ScheduleAssignmentLockedConflict($taskId);
            }

            // Locked placement already persists at the exact slot; keep it.
            return $locked;
        }

        // The proposal is an explicitly confirmed relocation: the task's prior
        // placement is replaced by the confirmed slot (the domain applies a
        // move by replacing the task's slot). Locked tasks are never touched,
        // and conflicted tasks are never deleted — they simply are not moved.
        // Each superseded placement is archived into history (ADR-015) in
        // this same transaction.
        foreach ($existing as $prior) {
            $this->assignments->deleteForUser($userId, $prior->id, ScheduleSupersession::rescheduleApply($newVersion->value));
        }

        return $this->assignments->create(ScheduleAssignment::create(
            userId: $userId,
            taskId: $taskId,
            date: $move->toSlot->start,
            startAt: $move->toSlot->start,
            endAt: $move->toSlot->end,
            source: ScheduleAssignmentSource::reschedule(),
            scheduleVersion: $newVersion->value,
        ));
    }

    private function proposalMatchesPersisted(int $userId, RescheduleProposal $proposal, ScheduleVersion $version): bool
    {
        $persisted = $this->assignments->listForUserAtVersion($userId, $version);

        if (count($persisted) !== count($proposal->moves)) {
            return false;
        }

        $byTask = [];
        foreach ($persisted as $assignment) {
            $byTask[$assignment->taskId] = $assignment;
        }

        foreach ($proposal->moves as $move) {
            $assignment = $byTask[(int) $move->taskId] ?? null;
            if ($assignment === null || ! $assignment->timeRange()->equals($move->toSlot)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, ScheduleAssignment>
     */
    private function existingForTask(int $userId, int $taskId): array
    {
        return array_values(array_filter(
            $this->assignments->listForTask($taskId),
            static fn (ScheduleAssignment $assignment) => $assignment->userId === $userId,
        ));
    }

    /**
     * @param  array<int, ScheduleAssignment>  $existing
     */
    private function lockedPlacement(array $existing): ?ScheduleAssignment
    {
        foreach ($existing as $assignment) {
            if ($assignment->locked) {
                return $assignment;
            }
        }

        return null;
    }
}
