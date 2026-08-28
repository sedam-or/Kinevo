<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApiTest extends TestCase
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
            'title' => $overrides['title'] ?? 'Default task',
            'status' => $overrides['status'] ?? 'backlog',
            'priority_tier' => $overrides['priority_tier'] ?? 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);
    }

    public function test_tasks_require_authentication(): void
    {
        $this->getJson('/api/v1/tasks')->assertStatus(401);
        $this->postJson('/api/v1/tasks', [])->assertStatus(401);
    }

    public function test_task_can_be_created(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/tasks', [
            'title' => 'Write report',
            'description' => 'Weekly report',
            'priority_tier' => 2,
            'estimated_minutes' => 45,
            'due_at' => '2026-08-20T10:00:00',
        ])->assertStatus(201)
            ->assertJsonPath('task.title', 'Write report')
            ->assertJsonPath('task.status', 'backlog')
            ->assertJsonPath('task.priority_tier', 2)
            ->assertJsonPath('task.progress', 0);

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Write report',
        ]);
    }

    public function test_tasks_are_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();

        $task = $this->createTask($other->id, ['title' => 'Not mine']);

        $this->withToken($token)->getJson("/api/v1/tasks/{$task->id}")->assertStatus(404);
        $this->withToken($token)->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Hijack'])->assertStatus(404);
        $this->withToken($token)->getJson('/api/v1/tasks')->assertJsonCount(0, 'tasks');
    }

    public function test_task_can_be_updated(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, ['title' => 'Old title']);

        $this->withToken($token)->putJson("/api/v1/tasks/{$task->id}", [
            'title' => 'New title',
            'priority_tier' => 1,
        ])->assertStatus(200)
            ->assertJsonPath('task.title', 'New title')
            ->assertJsonPath('task.priority_tier', 1);
    }

    public function test_task_status_lifecycle_transitions(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'scheduled'])
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'scheduled');

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'in_progress');

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'completed');
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, ['status' => 'completed']);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'backlog'])
            ->assertStatus(422);
    }

    public function test_stale_version_status_transition_returns_409(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'scheduled'])
            ->assertStatus(200);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/status", [
            'status' => 'in_progress',
            'version' => 1,
        ])->assertStatus(409)
            ->assertJsonPath('code', 'VERSION_CONFLICT');
    }

    public function test_subtask_can_be_added(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks", [
            'title' => 'Step one',
            'notes' => 'Collect inputs',
        ])->assertStatus(201)
            ->assertJsonPath('subtask.task_id', $task->id)
            ->assertJsonPath('subtask.title', 'Step one')
            ->assertJsonPath('subtask.completed', false);
    }

    public function test_toggle_subtask_recalculates_progress(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $one = $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'One'])->json('subtask');
        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'Two']);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks/{$one['id']}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('subtask.completed', true)
            ->assertJsonPath('task.progress', 50);
    }

    public function test_promote_subtask_creates_standalone_task(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $subtask = $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks", [
            'title' => 'Heavy item',
            'notes' => 'Long notes here',
        ])->json('subtask');

        $this->withToken($token)->postJson("/api/v1/subtasks/{$subtask['id']}/promote")
            ->assertStatus(200)
            ->assertJsonPath('task.title', 'Heavy item')
            ->assertJsonPath('task.status', 'backlog')
            ->assertJsonPath('task.estimated_minutes', 90);

        $this->assertDatabaseMissing('subtasks', ['id' => $subtask['id']]);
        $this->assertDatabaseHas('tasks', ['title' => 'Heavy item', 'user_id' => $user->id]);
    }

    public function test_partial_complete_creates_continuation_task(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, ['title' => 'Two-step build', 'status' => 'in_progress']);

        $done = $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks", [
            'title' => 'Done',
            'notes' => 'kept',
        ])->json('subtask');
        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'Remaining']);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks/{$done['id']}/toggle");

        $response = $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/partial-complete")
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'continued')
            ->assertJsonPath('continuation.status', 'backlog');

        $continuation = $response->json('continuation');
        $this->assertNotNull($continuation);
        $this->assertNotSame($task->id, $continuation['id']);

        $this->assertDatabaseHas('tasks', ['id' => $continuation['id'], 'status' => 'backlog']);
        $this->assertDatabaseHas('subtasks', ['task_id' => $continuation['id'], 'title' => 'Remaining']);
    }

    public function test_partial_complete_with_no_remaining_subtasks_completes_task(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, ['title' => 'Single step']);

        $subtask = $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'Only'])->json('subtask');
        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks/{$subtask['id']}/toggle");

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/partial-complete")
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'completed')
            ->assertJsonPath('task.progress', 100)
            ->assertJsonPath('continuation', null);
    }

    public function test_subtasks_are_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $otherToken = $other->createToken('owner')->plainTextToken;

        $task = $this->createTask($owner->id);
        $foreignTask = $this->createTask($other->id, ['title' => 'Foreign']);

        $owned = $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'Owned'])->json('subtask');

        $this->app['auth']->forgetGuards();
        $foreign = $this->withToken($otherToken)->postJson("/api/v1/tasks/{$foreignTask->id}/subtasks", ['title' => 'Foreign'])->json('subtask');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/v1/tasks/{$foreignTask->id}/subtasks/{$foreign['id']}/toggle")
            ->assertStatus(404);

        $this->withToken($token)->postJson("/api/v1/subtasks/{$foreign['id']}/promote")
            ->assertStatus(404);

        $this->assertDatabaseHas('subtasks', ['id' => $owned['id']]);
        $this->assertDatabaseHas('subtasks', ['id' => $foreign['id']]);
    }

    public function test_task_creation_validates_input(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/tasks', ['title' => ''])->assertStatus(422);
    }
}
