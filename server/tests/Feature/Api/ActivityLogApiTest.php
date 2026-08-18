<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
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

    public function test_logs_require_authentication(): void
    {
        $this->getJson('/api/v1/logs')->assertStatus(401);
        $this->postJson('/api/v1/export', ['format' => 'json'])->assertStatus(401);
    }

    public function test_completing_a_task_creates_one_activity_event(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, ['title' => 'Ship feature', 'status' => 'in_progress']);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'completed');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'task_completed',
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'title' => 'Ship feature',
        ]);

        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_completion_event_is_idempotent_on_retry(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, ['status' => 'in_progress']);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'completed'])->assertStatus(200);
        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'completed'])->assertStatus(422);

        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_checking_subtask_creates_subtask_completed_event(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $subtask = $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'One'])
            ->assertStatus(201)->json('subtask');

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks/{$subtask['id']}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('subtask.completed', true);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'subtask_completed',
            'entity_type' => 'subtask',
            'entity_id' => $subtask['id'],
        ]);
    }

    public function test_partial_complete_records_continued_event(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, ['status' => 'in_progress']);

        $done = $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'Done'])->json('subtask');
        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'Remaining']);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/subtasks/{$done['id']}/toggle");

        $response = $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/partial-complete")
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'continued');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'task_continued',
            'entity_id' => $task->id,
        ]);

        $log = ActivityLog::query()->where('user_id', $user->id)->where('event_type', 'task_continued')->first();
        $this->assertSame((int) $response->json('continuation.id'), $log->payload['continuation_task_id']);
    }

    public function test_logs_are_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $otherToken = $other->createToken('owner')->plainTextToken;

        ActivityLog::query()->create([
            'user_id' => $owner->id,
            'event_type' => 'task_completed',
            'entity_type' => 'task',
            'entity_id' => 1,
            'event_at' => now(),
        ]);
        ActivityLog::query()->create([
            'user_id' => $other->id,
            'event_type' => 'task_completed',
            'entity_type' => 'task',
            'entity_id' => 2,
            'event_at' => now(),
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/logs')->assertStatus(200);
        $this->assertCount(1, $response->json('logs'));
        $this->assertSame($owner->id, $response->json('logs.0.user_id'));
    }

    public function test_logs_support_date_and_type_filters(): void
    {
        [$user, $token] = $this->userWithToken();

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'event_type' => 'task_completed',
            'entity_type' => 'task',
            'entity_id' => 1,
            'event_at' => '2026-08-10 08:00:00',
        ]);
        ActivityLog::query()->create([
            'user_id' => $user->id,
            'event_type' => 'subtask_completed',
            'entity_type' => 'subtask',
            'entity_id' => 2,
            'event_at' => '2026-08-15 08:00:00',
        ]);

        $this->withToken($token)->getJson('/api/v1/logs?event_type=task_completed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'logs');

        $this->withToken($token)->getJson('/api/v1/logs?from=2026-08-14')
            ->assertStatus(200)
            ->assertJsonCount(1, 'logs')
            ->assertJsonPath('logs.0.event_type', 'subtask_completed');

        $this->withToken($token)->getJson('/api/v1/logs?from=2026-08-11&to=2026-08-12')
            ->assertStatus(200)
            ->assertJsonCount(0, 'logs');
    }

    public function test_export_json_contains_logs(): void
    {
        [$user, $token] = $this->userWithToken();

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'event_type' => 'task_completed',
            'entity_type' => 'task',
            'entity_id' => 1,
            'title' => 'Exported task',
            'event_at' => now(),
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/export', ['format' => 'json'])
            ->assertStatus(200);

        $this->assertSame('json', $response->json('format'));
        $this->assertSame('activity_logs.json', $response->json('filename'));

        $content = json_decode($response->json('content'), true);
        $this->assertCount(1, $content);
        $this->assertSame('task_completed', $content[0]['event_type']);
        $this->assertSame('Exported task', $content[0]['title']);
    }

    public function test_export_csv_contains_header_and_row(): void
    {
        [$user, $token] = $this->userWithToken();

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'event_type' => 'subtask_completed',
            'entity_type' => 'subtask',
            'entity_id' => 9,
            'title' => 'Csv row',
            'event_at' => now(),
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/export', ['format' => 'csv'])
            ->assertStatus(200);

        $this->assertSame('csv', $response->json('format'));
        $this->assertSame('activity_logs.csv', $response->json('filename'));

        $lines = preg_split('/\r\n|\n|\r/', trim($response->json('content')));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('id,event_type,entity_type,entity_id,title,event_at,payload', $lines[0]);
        $this->assertStringContainsString('subtask_completed', $lines[1]);
        $this->assertStringContainsString('Csv row', $lines[1]);
    }

    public function test_export_validates_format(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/export', ['format' => 'xml'])
            ->assertStatus(422);
    }

    public function test_export_is_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();

        ActivityLog::query()->create([
            'user_id' => $other->id,
            'event_type' => 'task_completed',
            'entity_type' => 'task',
            'entity_id' => 99,
            'event_at' => now(),
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/export', ['format' => 'json'])
            ->assertStatus(200);

        $this->assertSame('[]', trim($response->json('content')));
    }

    public function test_logs_validate_parameters(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->getJson('/api/v1/logs?event_type=bogus')
            ->assertStatus(422);

        $this->withToken($token)->getJson('/api/v1/logs?limit=0')
            ->assertStatus(422);
    }
}
