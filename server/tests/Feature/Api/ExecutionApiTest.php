<?php

namespace Tests\Feature\Api;

use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExecutionApiTest extends TestCase
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
            'title' => 'Task',
            'status' => $overrides['status'] ?? 'scheduled',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
            ...$overrides,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_execution_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/execution/active')->assertStatus(401);
        $this->getJson('/api/v1/execution')->assertStatus(401);
        $this->postJson('/api/v1/execution/start', ['task_id' => 1])->assertStatus(401);
    }

    public function test_timer_can_start_pause_resume_and_abandon(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $started = $this->withToken($token)->postJson('/api/v1/execution/start', ['task_id' => $task->id])
            ->assertStatus(201)
            ->assertJsonPath('execution.status', 'running');

        $sessionId = $started->json('execution.id');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);

        $paused = $this->withToken($token)->postJson("/api/v1/execution/{$sessionId}/pause")
            ->assertStatus(200)
            ->assertJsonPath('execution.status', 'paused');

        $resumed = $this->withToken($token)->postJson("/api/v1/execution/{$sessionId}/resume")
            ->assertStatus(200)
            ->assertJsonPath('execution.status', 'running');

        $abandoned = $this->withToken($token)->postJson("/api/v1/execution/{$sessionId}/abandon")
            ->assertStatus(200)
            ->assertJsonPath('execution.status', 'abandoned');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'task_abandoned',
        ]);
    }

    public function test_start_rejects_an_already_active_timer(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson('/api/v1/execution/start', ['task_id' => $task->id])
            ->assertStatus(201);

        $this->withToken($token)->postJson('/api/v1/execution/start', ['task_id' => $task->id])
            ->assertStatus(409);
    }

    public function test_completing_records_focus_session_and_completes_task(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        Carbon::setTestNow('2026-08-19 09:00:00');

        $started = $this->withToken($token)->postJson('/api/v1/execution/start', ['task_id' => $task->id]);

        $sessionId = $started->json('execution.id');

        Carbon::setTestNow('2026-08-19 09:45:00');

        $this->withToken($token)->postJson("/api/v1/execution/{$sessionId}/complete")
            ->assertStatus(200)
            ->assertJsonPath('execution.status', 'completed')
            ->assertJsonPath('focus_session.task_id', $task->id)
            ->assertJsonPath('focus_session.duration_minutes', 45)
            ->assertJsonPath('task.status', 'completed')
            ->assertJsonPath('continuation', null);

        $this->assertDatabaseHas('focus_sessions', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'duration_minutes' => 45,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'task_completed',
        ]);

        $this->assertDatabaseHas('progress_events', [
            'user_id' => $user->id,
            'event_type' => 'task_completed',
        ]);
    }

    public function test_completing_with_remaining_subtasks_creates_continuation(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        Subtask::query()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'title' => 'Remaining',
            'completed' => false,
            'sequence' => 1,
        ]);

        Carbon::setTestNow('2026-08-19 09:00:00');

        $started = $this->withToken($token)->postJson('/api/v1/execution/start', ['task_id' => $task->id]);
        $sessionId = $started->json('execution.id');

        Carbon::setTestNow('2026-08-19 09:45:00');

        $this->withToken($token)->postJson("/api/v1/execution/{$sessionId}/complete")
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'continued')
            ->assertJsonPath('continuation.status', 'backlog');

        $this->assertDatabaseHas('focus_sessions', ['user_id' => $user->id, 'task_id' => $task->id]);
    }

    public function test_active_endpoint_returns_current_timer(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->getJson('/api/v1/execution/active')
            ->assertStatus(200)
            ->assertJsonPath('execution', null);

        $this->withToken($token)->postJson('/api/v1/execution/start', ['task_id' => $task->id])
            ->assertStatus(201);

        $this->withToken($token)->getJson('/api/v1/execution/active')
            ->assertStatus(200)
            ->assertJsonPath('execution.status', 'running');
    }

    public function test_sessions_are_scoped_to_owner(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $task = $this->createTask($user->id);

        $started = $this->withToken($token)->postJson('/api/v1/execution/start', ['task_id' => $task->id]);
        $sessionId = $started->json('execution.id');

        $otherToken = $other->createToken('owner')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->postJson("/api/v1/execution/{$sessionId}/pause")
            ->assertStatus(404);
    }
}
