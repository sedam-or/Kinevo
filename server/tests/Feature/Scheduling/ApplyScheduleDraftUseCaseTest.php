<?php

namespace Tests\Feature\Scheduling;

use App\Application\Scheduling\ApplyScheduleDraftUseCase;
use App\Application\Scheduling\ScheduleApplyResult;
use App\Domain\Scheduling\DraftAssignment;
use App\Domain\Scheduling\ScheduleAssignmentLockedConflict;
use App\Domain\Scheduling\ScheduleAssignmentOverlap;
use App\Domain\Scheduling\ScheduleDraft;
use App\Domain\Scheduling\ScheduleVersionConflict;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class ApplyScheduleDraftUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private ApplyScheduleDraftUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = app(ApplyScheduleDraftUseCase::class);
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

    private function draft(array $assignments): ScheduleDraft
    {
        return new ScheduleDraft($assignments, []);
    }

    private function assignment(int $taskId, string $start, string $end): DraftAssignment
    {
        return new DraftAssignment((string) $taskId, 'Task', TimeRange::from($start, $end));
    }

    private function assertAssignmentCount(int $userId, int $expected): void
    {
        $this->assertSame(
            $expected,
            DB::table('task_assignments')->where('user_id', $userId)->count(),
        );
    }

    public function test_apply_persists_draft_assignments_at_next_version(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        $result = $this->applyDraft($user->id, $this->draft([
            $this->assignment($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
            $this->assignment($taskB->id, '2026-08-19T10:00:00', '2026-08-19T11:00:00'),
        ]), new ScheduleVersion(1));

        $this->assertTrue($result->applied);
        $this->assertSame(2, $result->version->value);
        $this->assertCount(2, $result->assignments);

        $this->assertDatabaseHas('task_assignments', [
            'user_id' => $user->id,
            'task_id' => $taskA->id,
            'start_at' => '2026-08-19 09:00:00',
            'schedule_version' => 2,
            'source' => 'draft',
        ]);
        $this->assertAssignmentCount($user->id, 2);
    }

    public function test_first_apply_on_empty_schedule_requires_base_version_one(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $result = $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ]), new ScheduleVersion(1));

        $this->assertTrue($result->applied);
        $this->assertSame(2, $result->version->value);
    }

    public function test_retry_same_draft_is_idempotent_and_does_not_duplicate(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        $draft = $this->draft([
            $this->assignment($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
            $this->assignment($taskB->id, '2026-08-19T10:00:00', '2026-08-19T11:00:00'),
        ]);

        $first = $this->applyDraft($user->id, $draft, new ScheduleVersion(1));
        $this->assertTrue($first->applied);

        $retry = $this->applyDraft($user->id, $draft, new ScheduleVersion(1));

        $this->assertFalse($retry->applied);
        $this->assertSame(2, $retry->version->value);
        $this->assertSame([], $retry->assignments);
        $this->assertAssignmentCount($user->id, 2);
    }

    public function test_stale_schedule_version_throws_conflict(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ]), new ScheduleVersion(1));

        $this->expectException(ScheduleVersionConflict::class);

        // Draft generated against version 1 is now stale (schedule at version 2).
        $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-20T09:00:00', '2026-08-20T10:00:00'),
        ]), new ScheduleVersion(1));
    }

    public function test_apply_replaces_prior_auto_placements_but_not_manual_or_locked(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        // First draft auto-places the task.
        $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ]), new ScheduleVersion(1));

        // Second draft moves the task; prior auto placement is superseded.
        $result = $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
        ]), new ScheduleVersion(2));

        $this->assertTrue($result->applied);
        $this->assertSame(3, $result->version->value);
        $this->assertAssignmentCount($user->id, 1);
        $this->assertDatabaseHas('task_assignments', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'start_at' => '2026-08-19 14:00:00',
            'schedule_version' => 3,
        ]);
    }

    public function test_locked_assignment_at_same_slot_is_kept_not_duplicated(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ]), new ScheduleVersion(1));

        // Lock the existing placement, then re-apply a draft keeping the slot.
        $assignmentModel = DB::table('task_assignments')
            ->where('user_id', $user->id)
            ->first();
        DB::table('task_assignments')
            ->where('id', $assignmentModel->id)
            ->update(['locked' => true]);

        $result = $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ]), new ScheduleVersion(2));

        $this->assertTrue($result->applied);
        $this->assertAssignmentCount($user->id, 1);
        $this->assertDatabaseHas('task_assignments', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'locked' => true,
        ]);
    }

    public function test_draft_that_moves_locked_assignment_is_rejected(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ]), new ScheduleVersion(1));

        DB::table('task_assignments')
            ->where('user_id', $user->id)
            ->update(['locked' => true]);

        $this->expectException(ScheduleAssignmentLockedConflict::class);

        $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
        ]), new ScheduleVersion(2));
    }

    public function test_invalid_draft_never_partially_persists(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $other = $this->createUser();
        $foreignTask = $this->createTask($other->id, 'Foreign');

        try {
            $this->applyDraft($user->id, $this->draft([
                $this->assignment($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
                $this->assignment($foreignTask->id, '2026-08-19T10:00:00', '2026-08-19T11:00:00'),
            ]), new ScheduleVersion(1));
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            $this->assertAssignmentCount($user->id, 0);
        }
    }

    public function test_overlapping_draft_assignments_are_rejected_and_rolled_back(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        $this->expectException(ScheduleAssignmentOverlap::class);

        $this->applyDraft($user->id, $this->draft([
            $this->assignment($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
            $this->assignment($taskB->id, '2026-08-19T09:30:00', '2026-08-19T10:30:00'),
        ]), new ScheduleVersion(1));

        $this->assertAssignmentCount($user->id, 0);
    }

    public function test_manual_placement_is_never_superseded(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $manual = DB::table('task_assignments')->insertGetId([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'date' => '2026-08-19',
            'start_at' => '2026-08-19T09:00:00',
            'end_at' => '2026-08-19T10:00:00',
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'source' => 'manual',
            'schedule_version' => 1,
            'locked' => false,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
        ]), new ScheduleVersion(1));

        // The manual placement survives at its slot; the draft adds another slot.
        $this->assertTrue($result->applied);
        $this->assertDatabaseHas('task_assignments', ['id' => $manual]);
        $this->assertSame(2, $result->version->value);
        $this->assertAssignmentCount($user->id, 2);
    }

    private function applyDraft(int $userId, ScheduleDraft $draft, ScheduleVersion $baseVersion): ScheduleApplyResult
    {
        return $this->useCase->__invoke($userId, $draft, $baseVersion);
    }

    // ------------------------------------------------------------------
    // ES-IMPL-06A — schedule assignment history (ADR-015)
    // ------------------------------------------------------------------

    private function historyCount(int $userId): int
    {
        return (int) DB::table('schedule_assignment_history')->where('user_id', $userId)->count();
    }

    public function test_schedule_assignment_history_table_exists(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('schedule_assignment_history'));
    }

    public function test_superseded_placement_is_archived_with_provenance(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ]), new ScheduleVersion(1));

        $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
        ]), new ScheduleVersion(2));

        $this->assertSame(1, $this->historyCount($user->id));
        $this->assertDatabaseHas('schedule_assignment_history', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'start_at' => '2026-08-19 09:00:00',
            'schedule_version' => 2,
            'superseded_by_schedule_version' => 3,
            'superseded_by' => 'draft',
        ]);

        // ADR-015 minimum query surface: the placement timeline is
        // reconstructable per task.
        $timeline = app(\App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository::class)
            ->historyForTask($user->id, $task->id);
        $this->assertCount(1, $timeline);
        $this->assertSame('2026-08-19T09:00:00.000000Z', $timeline[0]['start_at']);
        $this->assertSame('draft', $timeline[0]['superseded_by']);
    }

    public function test_failed_apply_writes_no_partial_history(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        // A is auto-placed at 09:00–10:00 (draft v1).
        $this->applyDraft($user->id, $this->draft([
            $this->assignment($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ]), new ScheduleVersion(1));

        // B holds a manual placement the new draft for A would overlap.
        DB::table('task_assignments')->insert([
            'user_id' => $user->id,
            'task_id' => $taskB->id,
            'date' => '2026-08-19',
            'start_at' => '2026-08-19 09:30:00',
            'end_at' => '2026-08-19 10:30:00',
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'source' => 'manual',
            'schedule_version' => 1,
            'locked' => false,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The draft supersedes A's prior placement, then fails on the overlap
        // — the whole transaction (history + live mutation) must roll back.
        $this->expectException(\App\Domain\Scheduling\ScheduleAssignmentOverlap::class);
        $this->applyDraft($user->id, $this->draft([
            $this->assignment($taskA->id, '2026-08-19T09:30:00', '2026-08-19T10:30:00'),
        ]), new ScheduleVersion(2));
    }

    public function test_failed_apply_rolls_back_history(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        $this->applyDraft($user->id, $this->draft([
            $this->assignment($taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ]), new ScheduleVersion(1));

        DB::table('task_assignments')->insert([
            'user_id' => $user->id,
            'task_id' => $taskB->id,
            'date' => '2026-08-19',
            'start_at' => '2026-08-19 09:30:00',
            'end_at' => '2026-08-19 10:30:00',
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'source' => 'manual',
            'schedule_version' => 1,
            'locked' => false,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->applyDraft($user->id, $this->draft([
                $this->assignment($taskA->id, '2026-08-19T09:30:00', '2026-08-19T10:30:00'),
            ]), new ScheduleVersion(2));
            $this->fail('Expected the overlapping draft to fail.');
        } catch (\App\Domain\Scheduling\ScheduleAssignmentOverlap) {
        }

        $this->assertSame(0, $this->historyCount($user->id), 'A failed apply must archive nothing.');
        $this->assertAssignmentCount($user->id, 2);
    }

    public function test_idempotent_reapply_does_not_duplicate_history(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ]), new ScheduleVersion(1));

        $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
        ]), new ScheduleVersion(2));

        // Idempotent retry of the same (already-applied) draft.
        $result = $this->applyDraft($user->id, $this->draft([
            $this->assignment($task->id, '2026-08-19T14:00:00', '2026-08-19T15:00:00'),
        ]), new ScheduleVersion(2));

        $this->assertFalse($result->applied);
        $this->assertSame(1, $this->historyCount($user->id));
    }
}
