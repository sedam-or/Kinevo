<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FocusSessionsApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createTask(int $userId): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => 'Task',
            'status' => 'scheduled',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);
    }

    public function test_focus_sessions_require_authentication(): void
    {
        $this->getJson('/api/v1/focus-sessions')->assertStatus(401);
        $this->postJson('/api/v1/focus-sessions', [
            'started_at' => '2026-08-18 09:00:00',
            'ended_at' => '2026-08-18 09:45:00',
        ])->assertStatus(401);
        $this->getJson('/api/v1/focus-sessions/recommendation')->assertStatus(401);
    }

    public function test_session_can_be_recorded(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/focus-sessions', [
            'started_at' => '2026-08-18 09:00:00',
            'ended_at' => '2026-08-18 09:45:00',
        ])->assertStatus(201)
            ->assertJsonPath('session.duration_minutes', 45);

        $this->assertDatabaseHas('focus_sessions', [
            'user_id' => $user->id,
            'duration_minutes' => 45,
        ]);
    }

    public function test_session_interval_is_validated(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/focus-sessions', [
            'started_at' => '2026-08-18 09:45:00',
            'ended_at' => '2026-08-18 09:00:00',
        ])->assertStatus(422);

        $this->assertDatabaseCount('focus_sessions', 0);
    }

    public function test_session_task_must_be_owned(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $foreignTask = $this->createTask($other->id);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/focus-sessions', [
            'task_id' => $foreignTask->id,
            'started_at' => '2026-08-18 09:00:00',
            'ended_at' => '2026-08-18 09:30:00',
        ])->assertStatus(404);

        $this->assertDatabaseCount('focus_sessions', 0);
    }

    public function test_sessions_can_be_listed_and_filtered(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson('/api/v1/focus-sessions', [
            'started_at' => '2026-08-18 09:00:00',
            'ended_at' => '2026-08-18 09:40:00',
        ])->assertStatus(201);

        $this->withToken($token)->postJson('/api/v1/focus-sessions', [
            'task_id' => $task->id,
            'started_at' => '2026-08-18 10:00:00',
            'ended_at' => '2026-08-18 10:50:00',
        ])->assertStatus(201);

        $this->withToken($token)->getJson('/api/v1/focus-sessions')
            ->assertStatus(200)
            ->assertJsonCount(2, 'sessions');

        $this->withToken($token)->getJson("/api/v1/focus-sessions?task_id={$task->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'sessions')
            ->assertJsonPath('sessions.0.duration_minutes', 50);
    }

    public function test_recommendation_falls_back_to_baseline_without_history(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->getJson('/api/v1/focus-sessions/recommendation')
            ->assertStatus(200)
            ->assertJsonPath('recommended_minutes', 45)
            ->assertJsonPath('basis', 'baseline')
            ->assertJsonPath('sample_count', 0);
    }

    public function test_recommendation_uses_recent_completion_patterns(): void
    {
        [$user, $token] = $this->userWithToken();

        foreach ([40, 45, 50] as $minutes) {
            $this->withToken($token)->postJson('/api/v1/focus-sessions', [
                'started_at' => '2026-08-18 09:00:00',
                'ended_at' => "2026-08-18 09:00:00 + {$minutes} minutes",
            ])->assertStatus(201);
        }

        $this->withToken($token)->getJson('/api/v1/focus-sessions/recommendation')
            ->assertStatus(200)
            ->assertJsonPath('recommended_minutes', 45)
            ->assertJsonPath('basis', 'user_patterns')
            ->assertJsonPath('sample_count', 3);
    }

    public function test_sessions_are_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();

        $this->withToken($token)->postJson('/api/v1/focus-sessions', [
            'started_at' => '2026-08-18 09:00:00',
            'ended_at' => '2026-08-18 09:30:00',
        ])->assertStatus(201);

        $otherToken = $other->createToken('owner')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/focus-sessions')
            ->assertStatus(200)
            ->assertJsonCount(0, 'sessions');
    }
}
