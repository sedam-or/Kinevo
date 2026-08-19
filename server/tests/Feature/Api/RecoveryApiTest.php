<?php

namespace Tests\Feature\Api;

use App\Models\Program;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecoveryApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createTask(int $userId, array $overrides = []): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $overrides['title'] ?? 'Recovered task',
            'status' => $overrides['status'] ?? 'missed',
            'priority_tier' => $overrides['priority_tier'] ?? 3,
            'progress_mode' => 'derived',
            'progress' => 0,
            'due_at' => $overrides['due_at'] ?? null,
            'program_id' => $overrides['program_id'] ?? null,
        ]);
    }

    private function createProgram(int $userId, string $status = 'active'): Program
    {
        return Program::query()->create([
            'user_id' => $userId,
            'name' => 'Recovery program',
            'workload_type' => 'flexible',
            'status' => $status,
            'priority_tier' => 3,
        ]);
    }

    public function test_recovery_requires_authentication(): void
    {
        $this->getJson('/api/v1/recovery')->assertStatus(401);
        $this->postJson('/api/v1/recovery/1', ['action' => 'reschedule'])->assertStatus(401);
    }

    public function test_recovery_lists_missed_tasks_by_nearest_deadline(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->createTask($user->id, ['title' => 'Later', 'due_at' => '2026-08-20 10:00:00']);
        $this->createTask($user->id, ['title' => 'Nearest', 'due_at' => '2026-08-19 09:00:00']);
        $this->createTask($user->id, ['title' => 'No deadline']);

        $response = $this->withToken($token)->getJson('/api/v1/recovery')
            ->assertStatus(200)
            ->assertJsonCount(3, 'recovery');

        $this->assertSame(
            ['Nearest', 'Later', 'No deadline'],
            array_column($response->json('recovery'), 'title'),
        );

        $this->assertSame(
            ['reschedule', 'complete', 'backlog'],
            $response->json('recovery.0.allowed_actions'),
        );
        $this->assertNull($response->json('recovery.0.invalid_reason'));
    }

    public function test_recovery_is_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();

        $this->createTask($owner->id, ['title' => 'Mine']);
        $this->createTask($other->id, ['title' => 'Foreign']);

        $this->withToken($token)->getJson('/api/v1/recovery')
            ->assertStatus(200)
            ->assertJsonCount(1, 'recovery')
            ->assertJsonPath('recovery.0.title', 'Mine');
    }

    public function test_reschedule_moves_task_to_scheduled(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson("/api/v1/recovery/{$task->id}", ['action' => 'reschedule'])
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'scheduled');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'scheduled']);
    }

    public function test_reschedule_can_update_due_date(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson("/api/v1/recovery/{$task->id}", [
            'action' => 'reschedule',
            'due_at' => '2026-08-18 18:00:00',
        ])->assertStatus(200);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'scheduled', 'due_at' => '2026-08-18 18:00:00']);
    }

    public function test_complete_marks_task_done_and_logs_activity(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson("/api/v1/recovery/{$task->id}", ['action' => 'complete'])
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'completed');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed']);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'task_completed',
            'entity_id' => $task->id,
        ]);
    }

    public function test_backlog_keeps_task_in_backlog(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson("/api/v1/recovery/{$task->id}", ['action' => 'backlog'])
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'backlog');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'backlog']);
    }

    public function test_non_missed_task_is_rejected(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, ['status' => 'scheduled']);

        $this->withToken($token)->postJson("/api/v1/recovery/{$task->id}", ['action' => 'reschedule'])
            ->assertStatus(422);
    }

    public function test_invalid_action_is_rejected(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson("/api/v1/recovery/{$task->id}", ['action' => 'explode'])
            ->assertStatus(422);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'missed']);
    }

    public function test_reschedule_withheld_for_completed_program(): void
    {
        [$user, $token] = $this->userWithToken();
        $program = $this->createProgram($user->id, 'completed');
        $task = $this->createTask($user->id, ['program_id' => $program->id]);

        $this->withToken($token)->getJson('/api/v1/recovery')
            ->assertStatus(200)
            ->assertJsonPath('recovery.0.invalid_reason', 'program_completed')
            ->assertJsonPath('recovery.0.allowed_actions', ['complete', 'backlog']);

        $this->withToken($token)->postJson("/api/v1/recovery/{$task->id}", ['action' => 'reschedule'])
            ->assertStatus(422);

        // Manual disposition remains available.
        $this->withToken($token)->postJson("/api/v1/recovery/{$task->id}", ['action' => 'backlog'])
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'backlog');
    }

    public function test_recovery_resolution_is_owner_scoped(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();

        $foreign = $this->createTask($other->id);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/v1/recovery/{$foreign->id}", ['action' => 'backlog'])
            ->assertStatus(404);
    }
}
