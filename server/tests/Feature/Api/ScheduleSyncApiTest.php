<?php

namespace Tests\Feature\Api;

use App\Application\Scheduling\SetAssignmentLockUseCase;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Contracts\ScheduleReviewRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * ADR-016 §2.2/§2.4 — manual Sync Now contract: deterministic diff over the
 * current Effective Landscape, never a write, deterministic lock contention.
 */
final class ScheduleSyncApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createTask(int $userId, string $title = 'Flexible work'): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'status' => 'scheduled',
            'priority_tier' => 2,
            'estimated_minutes' => 60,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);
    }

    private function place(int $userId, int $taskId, string $date, string $start, string $end, int $version = 1): void
    {
        app(ScheduleAssignmentRepository::class)->create(ScheduleAssignment::create(
            userId: $userId,
            taskId: $taskId,
            date: $date,
            startAt: $start,
            endAt: $end,
            source: ScheduleAssignmentSource::draft(),
            scheduleVersion: $version,
        ));
    }

    private function block(int $userId, string $start, string $end): void
    {
        app(HardLandscapeRepository::class)->create(
            HardLandscapeEvent::create($userId, 'New reality', HardLandscapeType::oneTime(), $start, $end),
        );
    }

    public function test_sync_requires_authentication(): void
    {
        $this->postJson('/api/v1/schedule/sync')->assertStatus(401);
    }

    public function test_no_changes_when_schedule_is_consistent(): void
    {
        [$user, $token] = $this->userWithToken();

        $response = $this->withToken($token)->postJson('/api/v1/schedule/sync');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'no_changes')
            ->assertJsonPath('needs_review', false)
            ->assertJsonPath('proposal', null);
    }

    public function test_sync_returns_proposal_when_reality_blocks_a_placement(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $date = now()->toDateString();
        $this->place($user->id, $task->id, $date, $date.' 10:00', $date.' 11:00');
        // Reality change AFTER the placement: an overlapping block now exists.
        $this->block($user->id, $date.' 10:30', $date.' 12:00');

        $response = $this->withToken($token)->postJson('/api/v1/schedule/sync');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'proposal')
            ->assertJsonStructure(['proposal' => ['base_version', 'new_version', 'moves' => [['task_id', 'from', 'to']], 'conflict_task_ids']]);

        // Sync is read-only: the placement is untouched until explicit apply.
        $this->assertSame(
            1,
            app(ScheduleAssignmentRepository::class)->currentScheduleVersion($user->id)->value,
        );
    }

    public function test_no_changes_clears_the_needs_review_flag(): void
    {
        [$user, $token] = $this->userWithToken();
        app(ScheduleReviewRepository::class)->markNeedsReview($user->id, ['hard_landscape_created' => [1]], 1);

        $response = $this->withToken($token)->postJson('/api/v1/schedule/sync');

        $response->assertStatus(200)->assertJsonPath('status', 'no_changes');
        $this->assertFalse(app(ScheduleReviewRepository::class)->findForUser($user->id)->needsReview);
    }

    public function test_locked_work_is_never_proposed_for_a_move(): void
    {
        [$user, $token] = $this->userWithToken();
        $lockedTask = $this->createTask($user->id, 'Locked work');
        $flexibleTask = $this->createTask($user->id, 'Flexible work');

        $date = now()->toDateString();
        // Locked placement overlapping a NEW block → the rescheduler skips it.
        app(ScheduleAssignmentRepository::class)->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $lockedTask->id,
            date: $date,
            startAt: $date.' 10:00',
            endAt: $date.' 11:00',
            source: ScheduleAssignmentSource::draft(),
            scheduleVersion: 1,
        ));
        app(SetAssignmentLockUseCase::class)->__invoke(
            $user->id,
            $lockedTask->id,
            true,
        );
        $this->block($user->id, $date.' 10:30', $date.' 12:00');

        $response = $this->withToken($token)->postJson('/api/v1/schedule/sync');

        $response->assertStatus(200);
        $moves = $response->json('proposal.moves') ?? [];
        foreach ($moves as $move) {
            $this->assertNotEquals((string) $lockedTask->id, (string) $move['task_id']);
        }
    }

    public function test_synced_proposal_applies_through_the_existing_endpoint(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);
        $date = now()->toDateString();
        $this->place($user->id, $task->id, $date, $date.' 10:00', $date.' 11:00');
        $this->block($user->id, $date.' 10:30', $date.' 12:00');

        $sync = $this->withToken($token)->postJson('/api/v1/schedule/sync');
        $proposal = $sync->json('proposal');
        $this->assertNotNull($proposal);

        $apply = $this->withToken($token)->postJson('/api/v1/schedule/reschedule/apply', [
            'proposal' => $proposal,
            'base_version' => $proposal['base_version'],
        ]);

        $apply->assertStatus(200)->assertJsonPath('applied', true);
        $this->assertSame(
            2,
            app(ScheduleAssignmentRepository::class)->currentScheduleVersion($user->id)->value,
        );
    }

    public function test_concurrent_sync_returns_run_in_progress(): void
    {
        [$user, $token] = $this->userWithToken();

        $lock = Cache::lock('schedule:sync:'.$user->id, 60);
        $this->assertTrue($lock->get());

        try {
            $response = $this->withToken($token)->postJson('/api/v1/schedule/sync');

            $response->assertStatus(200)->assertJsonPath('status', 'run_in_progress');
        } finally {
            $lock->release();
        }
    }

    public function test_horizon_is_bounded_to_fourteen_days(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/schedule/sync', [
            'from' => '2026-01-01',
            'to' => '2026-01-20',
        ])->assertStatus(422);
    }
}
