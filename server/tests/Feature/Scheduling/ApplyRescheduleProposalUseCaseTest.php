<?php

namespace Tests\Feature\Scheduling;

use App\Application\Scheduling\ApplyRescheduleProposalUseCase;
use App\Application\Scheduling\RescheduleApplyResult;
use App\Domain\Scheduling\RescheduleProposal;
use App\Domain\Scheduling\ScheduleAssignmentLockedConflict;
use App\Domain\Scheduling\ScheduleAssignmentOverlap;
use App\Domain\Scheduling\ScheduleVersionConflict;
use App\Domain\Scheduling\TaskMove;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class ApplyRescheduleProposalUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private ApplyRescheduleProposalUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = app(ApplyRescheduleProposalUseCase::class);
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createTask(int $userId, string $title = 'Task'): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'status' => 'backlog',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);
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

    private function assertAssignmentCount(int $userId, int $expected): void
    {
        $this->assertSame(
            $expected,
            DB::table('task_assignments')->where('user_id', $userId)->count(),
        );
    }

    private function placeManual(int $userId, int $taskId, string $start, string $end, int $scheduleVersion = 1, bool $locked = false): int
    {
        return DB::table('task_assignments')->insertGetId([
            'user_id' => $userId,
            'task_id' => $taskId,
            'date' => substr($start, 0, 10),
            'start_at' => $start,
            'end_at' => $end,
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'source' => 'manual',
            'schedule_version' => $scheduleVersion,
            'locked' => $locked,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_apply_persists_moves_at_next_version(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');

        $this->placeManual($user->id, $taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $result = $this->useCase->__invoke($user->id, $this->proposal(
            new ScheduleVersion(1),
            [$this->move($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
        ));

        $this->assertTrue($result->applied);
        $this->assertSame(2, $result->version->value);
        $this->assertCount(1, $result->assignments);

        $this->assertDatabaseHas('task_assignments', [
            'user_id' => $user->id,
            'task_id' => $taskA->id,
            'start_at' => '2026-08-19 14:00:00',
            'schedule_version' => 2,
            'source' => 'reschedule',
        ]);
        $this->assertAssignmentCount($user->id, 1);
    }

    public function test_retry_same_proposal_is_idempotent_and_does_not_duplicate(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');

        $this->placeManual($user->id, $taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $proposal = $this->proposal(
            new ScheduleVersion(1),
            [$this->move($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
        );

        $this->assertTrue($this->useCase->__invoke($user->id, $proposal)->applied);

        $retry = $this->useCase->__invoke($user->id, $proposal);

        $this->assertFalse($retry->applied);
        $this->assertSame(2, $retry->version->value);
        $this->assertSame([], $retry->assignments);
        $this->assertAssignmentCount($user->id, 1);
    }

    public function test_stale_schedule_version_throws_conflict(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');

        $this->placeManual($user->id, $taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $this->placeManual($user->id, $taskA->id, '2026-08-19T10:00:00', '2026-08-19T11:00:00', scheduleVersion: 2);

        $this->expectException(ScheduleVersionConflict::class);

        // Proposal computed against version 1 is now stale (schedule at version 2).
        $this->useCase->__invoke($user->id, $this->proposal(
            new ScheduleVersion(1),
            [$this->move($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
        ));
    }

    public function test_locked_assignment_is_never_moved(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');

        $this->placeManual($user->id, $taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00', locked: true);

        $this->expectException(ScheduleAssignmentLockedConflict::class);

        $this->useCase->__invoke($user->id, $this->proposal(
            new ScheduleVersion(1),
            [$this->move($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
        ));
    }

    public function test_conflicted_task_is_kept_not_deleted(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        $this->placeManual($user->id, $taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $this->placeManual($user->id, $taskB->id, '2026-08-19T11:00:00', '2026-08-19T12:00:00');

        $result = $this->useCase->__invoke($user->id, $this->proposal(
            new ScheduleVersion(1),
            [$this->move($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
            conflicts: [(string) $taskB->id],
        ));

        $this->assertTrue($result->applied);
        $this->assertSame([(string) $taskB->id], $result->conflictTaskIds);

        // Task B keeps its placement; nothing is deleted.
        $this->assertAssignmentCount($user->id, 2);
        $this->assertDatabaseHas('task_assignments', [
            'user_id' => $user->id,
            'task_id' => $taskB->id,
            'start_at' => '2026-08-19 11:00:00',
        ]);
    }

    public function test_invalid_proposal_never_partially_persists(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $other = $this->createUser();
        $foreignTask = $this->createTask($other->id, 'Foreign');

        $this->placeManual($user->id, $taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        try {
            $this->useCase->__invoke($user->id, $this->proposal(
                new ScheduleVersion(1),
                [
                    $this->move($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
                    $this->move($foreignTask->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T16:00:00', '2026-08-19T17:00:00'),
                ],
            ));
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            $this->assertAssignmentCount($user->id, 1);
        }
    }

    public function test_overlapping_moves_are_rejected_and_rolled_back(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        $this->placeManual($user->id, $taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $this->placeManual($user->id, $taskB->id, '2026-08-19T11:00:00', '2026-08-19T12:00:00');

        $this->expectException(ScheduleAssignmentOverlap::class);

        // Both tasks move into the same slot.
        $this->useCase->__invoke($user->id, $this->proposal(
            new ScheduleVersion(1),
            [
                $this->move($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
                $this->move($taskB->id, '2026-08-19T11:00:00', '2026-08-19T12:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
            ],
        ));

        $this->assertAssignmentCount($user->id, 2);
    }

    public function test_manual_placement_of_moved_task_is_superseded(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');

        $this->placeManual($user->id, $taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $result = $this->useCase->__invoke($user->id, $this->proposal(
            new ScheduleVersion(1),
            [$this->move($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
        ));

        $this->assertTrue($result->applied);
        $this->assertAssignmentCount($user->id, 1);
        $this->assertDatabaseHas('task_assignments', [
            'user_id' => $user->id,
            'task_id' => $taskA->id,
            'start_at' => '2026-08-19 14:00:00',
            'source' => 'reschedule',
        ]);
    }

    public function test_apply_result_exposes_version_and_conflicts(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        $this->placeManual($user->id, $taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $this->placeManual($user->id, $taskB->id, '2026-08-19T11:00:00', '2026-08-19T12:00:00');

        $result = $this->useCase->__invoke($user->id, $this->proposal(
            new ScheduleVersion(1),
            [$this->move($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00')],
            conflicts: [(string) $taskB->id],
        ));

        $this->assertInstanceOf(RescheduleApplyResult::class, $result);
        $this->assertSame(2, $result->version->value);
        $this->assertSame([(string) $taskB->id], $result->conflictTaskIds);
    }
}
