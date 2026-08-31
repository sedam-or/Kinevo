<?php

namespace Tests\Feature\Api;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ES-IMPL-07 — locked-task producer (ADR-015 locked-task contract):
 * the user is the only producer of `locked=true`; the scheduler and the
 * rescheduler never move locked placements.
 */
final class AssignmentLockApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function place(int $userId): array
    {
        $task = Task::query()->create([
            'user_id' => $userId,
            'title' => 'Fixed work',
            'status' => 'backlog',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);

        $assignment = app(ScheduleAssignmentRepository::class)->create(ScheduleAssignment::create(
            userId: $userId,
            taskId: $task->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T10:00:00',
            source: ScheduleAssignmentSource::draft(),
            scheduleVersion: 1,
        ));

        return [$task, $assignment];
    }

    public function test_lock_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/tasks/1/assignment/lock')->assertStatus(401);
        $this->postJson('/api/v1/tasks/1/assignment/unlock')->assertStatus(401);
    }

    public function test_user_can_lock_and_unlock_a_placement(): void
    {
        [$user, $token] = $this->userWithToken();
        [$task, $assignment] = $this->place($user->id);

        $this->withToken($token)
            ->postJson("/api/v1/tasks/{$task->id}/assignment/lock")
            ->assertStatus(200)
            ->assertJsonPath('assignment.locked', true);

        $this->withToken($token)
            ->postJson("/api/v1/tasks/{$task->id}/assignment/unlock")
            ->assertStatus(200)
            ->assertJsonPath('assignment.locked', false);
    }

    public function test_locking_is_idempotent(): void
    {
        [$user, $token] = $this->userWithToken();
        [$task] = $this->place($user->id);

        $first = $this->withToken($token)
            ->postJson("/api/v1/tasks/{$task->id}/assignment/lock")
            ->assertStatus(200)
            ->json('assignment');

        $again = $this->withToken($token)
            ->postJson("/api/v1/tasks/{$task->id}/assignment/lock")
            ->assertStatus(200)
            ->json('assignment');

        $this->assertSame($first['version'], $again['version'], 'A no-op lock must not bump the version.');
    }

    public function test_locking_records_activity(): void
    {
        [$user, $token] = $this->userWithToken();
        [$task] = $this->place($user->id);

        $this->withToken($token)
            ->postJson("/api/v1/tasks/{$task->id}/assignment/lock")
            ->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'assignment_locked',
        ]);
    }

    public function test_task_without_a_placement_returns_404(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = Task::query()->create([
            'user_id' => $user->id,
            'title' => 'Unplaced',
            'status' => 'backlog',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/tasks/{$task->id}/assignment/lock")
            ->assertStatus(404);
    }

    public function test_stale_version_returns_409(): void
    {
        [$user, $token] = $this->userWithToken();
        [$task] = $this->place($user->id);

        $this->withToken($token)
            ->postJson("/api/v1/tasks/{$task->id}/assignment/lock", ['version' => 99])
            ->assertStatus(409);
    }

    public function test_another_users_task_is_not_reachable(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();
        [$otherTask] = $this->place($other->id);

        $this->withToken($token)
            ->postJson("/api/v1/tasks/{$otherTask->id}/assignment/lock")
            ->assertStatus(404);
    }

    public function test_rescheduler_never_moves_a_locked_placement(): void
    {
        [$user, $token] = $this->userWithToken();
        [$task] = $this->place($user->id); // 09:00–10:00 on 2026-08-19.

        $this->withToken($token)
            ->postJson("/api/v1/tasks/{$task->id}/assignment/lock")
            ->assertStatus(200);

        // A Hard Landscape block lands exactly on the locked placement.
        app(HardLandscapeRepository::class)->create(
            HardLandscapeEvent::create(
                $user->id,
                'New commitment',
                HardLandscapeType::oneTime(),
                '2026-08-19T09:00:00',
                '2026-08-19T10:00:00',
            ),
        );

        $proposal = $this->withToken($token)
            ->postJson('/api/v1/schedule/reschedule', ['from' => '2026-08-19', 'to' => '2026-08-19'])
            ->assertStatus(200)
            ->json()['proposal'];

        $movedTaskIds = array_map(
            static fn (array $move) => (string) $move['task_id'],
            $proposal['moves'],
        );

        $this->assertNotContains((string) $task->id, $movedTaskIds, 'Locked placements are never moved by the rescheduler.');
    }
}
