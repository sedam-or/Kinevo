<?php

namespace Tests\Unit\Scheduling;

use App\Application\Scheduling\ApplyRescheduleProposalUseCase;
use App\Domain\Scheduling\RescheduleProposal;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleAssignmentLockedConflict;
use App\Domain\Scheduling\ScheduleVersionConflict;
use App\Domain\Scheduling\TaskMove;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeAssignmentStore;
use Tests\TestCase;

/**
 * Pure apply-orchestration tests using the real use case with an in-memory
 * repository double (no DB): version semantics, idempotent retry, locked
 * protection, and conflict visibility.
 */
final class ApplyRescheduleProposalUseCaseTest extends TestCase
{
    private FakeAssignmentStore $store;

    private ApplyRescheduleProposalUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new FakeAssignmentStore;
        $this->useCase = new ApplyRescheduleProposalUseCase($this->store);
    }

    private function move(int $taskId, string $fromStart, string $fromEnd, string $toStart, string $toEnd): TaskMove
    {
        return new TaskMove(
            (string) $taskId,
            'Task',
            TimeRange::from($fromStart, $fromEnd),
            TimeRange::from($toStart, $toEnd),
        );
    }

    private function proposal(ScheduleVersion $base, array $moves, array $conflicts = []): RescheduleProposal
    {
        return new RescheduleProposal($base, $base->next(), $moves, $conflicts);
    }

    private function seededAssignment(int $taskId, string $start, string $end, int $version, bool $locked = false): ScheduleAssignment
    {
        return ScheduleAssignment::create(
            userId: 1,
            taskId: $taskId,
            date: substr($start, 0, 10),
            startAt: $start,
            endAt: $end,
            source: ScheduleAssignmentSource::reschedule(),
            scheduleVersion: $version,
            locked: $locked,
        );
    }

    #[Test]
    public function retry_of_already_applied_proposal_is_idempotent(): void
    {
        $this->store->seed($this->seededAssignment(10, '2026-08-19T14:00:00', '2026-08-19T15:00:00', 2));

        $result = $this->useCase->__invoke(1, $this->proposal(
            new ScheduleVersion(1),
            [$this->move(10, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
        ));

        $this->assertFalse($result->applied);
        $this->assertSame(2, $result->version->value);
        $this->assertSame([], $result->assignments);
    }

    #[Test]
    public function stale_base_version_throws_conflict(): void
    {
        $this->store->seed($this->seededAssignment(10, '2026-08-19T14:00:00', '2026-08-19T15:00:00', 3));

        $this->expectException(ScheduleVersionConflict::class);

        // Proposal computed against version 1, but schedule moved to version 3.
        $this->useCase->__invoke(1, $this->proposal(
            new ScheduleVersion(1),
            [$this->move(10, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
        ));
    }

    #[Test]
    public function retry_is_not_idempotent_when_proposal_differs_from_persisted(): void
    {
        $this->store->seed($this->seededAssignment(10, '2026-08-19T09:00:00', '2026-08-19T10:00:00', 2));

        $this->expectException(ScheduleVersionConflict::class);

        // Same base version, but a different target slot than what was persisted.
        $this->useCase->__invoke(1, $this->proposal(
            new ScheduleVersion(1),
            [$this->move(10, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
        ));
    }

    #[Test]
    public function locked_assignment_at_different_slot_is_rejected(): void
    {
        $this->store->seed($this->seededAssignment(10, '2026-08-19T09:00:00', '2026-08-19T10:00:00', 1, locked: true));

        $this->expectException(ScheduleAssignmentLockedConflict::class);

        $this->useCase->__invoke(1, $this->proposal(
            new ScheduleVersion(1),
            [$this->move(10, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
        ));
    }

    #[Test]
    public function invalid_unknown_task_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->useCase->__invoke(1, $this->proposal(
            new ScheduleVersion(1),
            [$this->move(999, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
        ));
    }

    #[Test]
    public function conflict_task_ids_are_exposed_on_result(): void
    {
        $this->store->seed($this->seededAssignment(10, '2026-08-19T09:00:00', '2026-08-19T10:00:00', 1));

        $result = $this->useCase->__invoke(1, $this->proposal(
            new ScheduleVersion(1),
            [$this->move(10, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
            conflicts: ['20'],
        ));

        $this->assertTrue($result->applied);
        $this->assertSame(2, $result->version->value);
        $this->assertSame(['20'], $result->conflictTaskIds);
    }
}
