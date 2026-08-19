<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\DraftAssignment;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleAssignmentLockedConflict;
use App\Domain\Scheduling\ScheduleDraft;
use App\Domain\Scheduling\ScheduleVersionConflict;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use Illuminate\Support\Facades\DB;

/**
 * Explicitly apply an approved auto-schedule draft (FR-27; scheduling-engine
 * "Draft versus applied schedule"). Persists every placement atomically at the
 * next schedule version. Generating a draft never mutates the schedule; only
 * this explicit apply persists it.
 *
 * - Stale apply (current version differs from the version the draft was
 *   generated against) → ScheduleVersionConflict (HTTP 409).
 * - Re-applying an already-applied draft is an idempotent no-op success.
 * - Locked assignments are never moved or overwritten (FR-04/FR-27); prior
 *   auto-generated placements for the tasks the draft schedules are superseded.
 * - Any invalid placement (illegal overlap, unknown task) rolls the whole
 *   transaction back — an invalid draft never partially persists.
 */
final readonly class ApplyScheduleDraftUseCase
{
    public function __construct(
        private ScheduleAssignmentRepository $assignments,
    ) {}

    public function __invoke(int $userId, ScheduleDraft $draft, ScheduleVersion $baseVersion): ScheduleApplyResult
    {
        $current = $this->assignments->currentScheduleVersion($userId);

        // Idempotent retry: the exact draft was already applied exactly one
        // version ahead (successful earlier apply of the same draft).
        if ($current->value === $baseVersion->value + 1
            && $this->draftMatchesPersisted($userId, $draft, $current)) {
            return new ScheduleApplyResult($current, [], applied: false);
        }

        if (! $current->equals($baseVersion)) {
            throw new ScheduleVersionConflict($baseVersion, $current);
        }

        $newVersion = $current->next();

        $created = DB::transaction(function () use ($userId, $draft, $newVersion): array {
            $persisted = [];

            foreach ($draft->assignments as $planned) {
                $persisted[] = $this->place($userId, $planned, $newVersion);
            }

            return $persisted;
        });

        return new ScheduleApplyResult($newVersion, $created, applied: true);
    }

    private function place(int $userId, DraftAssignment $planned, ScheduleVersion $newVersion): ScheduleAssignment
    {
        $taskId = (int) $planned->taskId;
        $existing = $this->existingForTask($userId, $taskId);

        $locked = $this->lockedPlacement($existing);
        if ($locked !== null) {
            if (! $locked->timeRange()->equals($planned->slot)) {
                throw new ScheduleAssignmentLockedConflict($taskId);
            }

            // Locked placement already persists at the exact slot; keep it.
            return $locked;
        }

        // Supersede the task's prior auto-generated placements (never manual,
        // override, or locked) so the draft is the authoritative placement.
        foreach ($existing as $prior) {
            if ($this->isSuperseded($prior)) {
                $this->assignments->deleteForUser($userId, $prior->id);
            }
        }

        return $this->assignments->create(ScheduleAssignment::create(
            userId: $userId,
            taskId: $taskId,
            date: $planned->slot->start,
            startAt: $planned->slot->start,
            endAt: $planned->slot->end,
            source: ScheduleAssignmentSource::draft(),
            scheduleVersion: $newVersion->value,
        ));
    }

    private function draftMatchesPersisted(int $userId, ScheduleDraft $draft, ScheduleVersion $version): bool
    {
        $persisted = $this->assignments->listForUserAtVersion($userId, $version);

        if (count($persisted) !== count($draft->assignments)) {
            return false;
        }

        $byTask = [];
        foreach ($persisted as $assignment) {
            $byTask[$assignment->taskId] = $assignment;
        }

        foreach ($draft->assignments as $planned) {
            $assignment = $byTask[(int) $planned->taskId] ?? null;
            if ($assignment === null || ! $assignment->timeRange()->equals($planned->slot)) {
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

    private function isSuperseded(ScheduleAssignment $assignment): bool
    {
        if ($assignment->locked) {
            return false;
        }

        // User-driven placements are never silently removed by automation.
        return ! $assignment->source->equals(ScheduleAssignmentSource::manual())
            && ! $assignment->source->equals(ScheduleAssignmentSource::override());
    }
}
