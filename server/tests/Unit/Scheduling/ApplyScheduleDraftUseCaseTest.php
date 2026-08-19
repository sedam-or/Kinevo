<?php

namespace Tests\Unit\Scheduling;

use App\Application\Scheduling\ApplyScheduleDraftUseCase;
use App\Domain\Scheduling\DraftAssignment;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleAssignmentLockedConflict;
use App\Domain\Scheduling\ScheduleDraft;
use App\Domain\Scheduling\ScheduleVersionConflict;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeAssignmentStore;
use Tests\TestCase;

/**
 * Pure apply-orchestration tests using the real use case with an in-memory
 * repository double (no DB): version semantics, idempotent retry, and locked
 * protection.
 */
final class ApplyScheduleDraftUseCaseTest extends TestCase
{
    private FakeAssignmentStore $store;

    private ApplyScheduleDraftUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new FakeAssignmentStore;
        $this->useCase = new ApplyScheduleDraftUseCase($this->store);
    }

    private function assignment(int $taskId, string $start, string $end): DraftAssignment
    {
        return new DraftAssignment((string) $taskId, 'Task', TimeRange::from($start, $end));
    }

    #[Test]
    public function retry_of_already_applied_draft_is_idempotent(): void
    {
        $this->store->seed(
            ScheduleAssignment::create(
                userId: 1,
                taskId: 10,
                date: '2026-08-19',
                startAt: '2026-08-19T09:00:00',
                endAt: '2026-08-19T10:00:00',
                source: ScheduleAssignmentSource::draft(),
                scheduleVersion: 2,
            ),
        );

        $result = $this->useCase->__invoke(1, new ScheduleDraft([
            $this->assignment(10, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ], []), new ScheduleVersion(1));

        $this->assertFalse($result->applied);
        $this->assertSame(2, $result->version->value);
        $this->assertSame([], $result->assignments);
    }

    #[Test]
    public function stale_base_version_throws_conflict(): void
    {
        $this->store->seed(
            ScheduleAssignment::create(
                userId: 1,
                taskId: 10,
                date: '2026-08-19',
                startAt: '2026-08-19T09:00:00',
                endAt: '2026-08-19T10:00:00',
                source: ScheduleAssignmentSource::draft(),
                scheduleVersion: 3,
            ),
        );

        $this->expectException(ScheduleVersionConflict::class);

        // Draft generated against version 1, but schedule moved to version 3.
        $this->useCase->__invoke(1, new ScheduleDraft([
            $this->assignment(10, '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
        ], []), new ScheduleVersion(1));
    }

    #[Test]
    public function retry_is_not_idempotent_when_draft_differs_from_persisted(): void
    {
        $this->store->seed(
            ScheduleAssignment::create(
                userId: 1,
                taskId: 10,
                date: '2026-08-19',
                startAt: '2026-08-19T09:00:00',
                endAt: '2026-08-19T10:00:00',
                source: ScheduleAssignmentSource::draft(),
                scheduleVersion: 2,
            ),
        );

        $this->expectException(ScheduleVersionConflict::class);

        // Same base version, but a different placement than what was persisted.
        $this->useCase->__invoke(1, new ScheduleDraft([
            $this->assignment(10, '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
        ], []), new ScheduleVersion(1));
    }

    #[Test]
    public function locked_assignment_at_different_slot_is_rejected(): void
    {
        $this->store->seed(
            ScheduleAssignment::create(
                userId: 1,
                taskId: 10,
                date: '2026-08-19',
                startAt: '2026-08-19T09:00:00',
                endAt: '2026-08-19T10:00:00',
                source: ScheduleAssignmentSource::manual(),
                scheduleVersion: 1,
                locked: true,
            ),
        );

        $this->expectException(ScheduleAssignmentLockedConflict::class);

        $this->useCase->__invoke(1, new ScheduleDraft([
            $this->assignment(10, '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
        ], []), new ScheduleVersion(1));
    }

    #[Test]
    public function invalid_unknown_task_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->useCase->__invoke(1, new ScheduleDraft([
            $this->assignment(999, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ], []), new ScheduleVersion(1));
    }
}
