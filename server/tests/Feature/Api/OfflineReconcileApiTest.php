<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ADR-017 — offline mutation reconciliation contract: bounded allowlist,
 * idempotent replay, same-id/different-payload rejection, conflict semantics,
 * ownership isolation, batch behavior, retention.
 */
final class OfflineReconcileApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function envelope(string $operationId, string $type, ?int $entityId, array $payload, ?int $baseVersion = null): array
    {
        return [
            'protocol_version' => 1,
            'operation_id' => $operationId,
            'operation_type' => $type,
            'entity_type' => explode(':', $type)[0],
            'entity_id' => $entityId,
            'payload' => $payload,
            'base_version' => $baseVersion,
            'workspace_id' => null,
            'client_created_at' => '2026-08-31T00:00:00Z',
        ];
    }

    private function createTask(int $userId, string $title = 'Work', int $version = 1): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'status' => 'backlog',
            'priority_tier' => 2,
            'estimated_minutes' => 30,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => $version,
        ]);
    }

    public function test_reconcile_requires_authentication(): void
    {
        $this->postJson('/api/v1/sync/reconcile', ['operations' => []])->assertStatus(401);
    }

    public function test_first_apply_creates_the_entity_once(): void
    {
        [$user, $token] = $this->userWithToken();

        $response = $this->withToken($token)->postJson('/api/v1/sync/reconcile', [
            'operations' => [
                $this->envelope('op-task-1', 'task:create', null, ['title' => 'Offline task', 'estimated_minutes' => 30]),
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('outcomes.0.status', 'applied');
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('offline_operations', 1);
    }

    public function test_same_id_identical_payload_replay_does_not_duplicate(): void
    {
        [$user, $token] = $this->userWithToken();
        $op = $this->envelope('op-task-2', 'task:create', null, ['title' => 'Replay task']);

        $this->withToken($token)->postJson('/api/v1/sync/reconcile', ['operations' => [$op]])->assertStatus(200);
        $replay = $this->withToken($token)->postJson('/api/v1/sync/reconcile', ['operations' => [$op]]);

        $replay->assertStatus(200)
            ->assertJsonPath('outcomes.0.status', 'applied')
            ->assertJsonPath('outcomes.0.replay', true);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_same_id_different_payload_is_rejected_deterministically(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->withToken($token)->postJson('/api/v1/sync/reconcile', [
            'operations' => [$this->envelope('op-task-3', 'task:create', null, ['title' => 'First'])],
        ])->assertStatus(200);

        $reused = $this->withToken($token)->postJson('/api/v1/sync/reconcile', [
            'operations' => [$this->envelope('op-task-3', 'task:create', null, ['title' => 'Second'])],
        ]);

        $reused->assertStatus(200)
            ->assertJsonPath('outcomes.0.status', 'rejected')
            ->assertJsonPath('outcomes.0.code', 'REUSED');
        $this->assertDatabaseCount('tasks', 1);
        $this->assertSame('First', Task::query()->where('user_id', $user->id)->value('title'));
    }

    public function test_task_status_semantic_idempotency(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Work');
        $task->update(['status' => 'in_progress']);

        $complete = $this->envelope('op-complete-1', 'task:status', $task->id, ['status' => 'completed']);
        $this->withToken($token)->postJson('/api/v1/sync/reconcile', ['operations' => [$complete]])->assertStatus(200);

        // Replay the same completion — no re-transition, recorded outcome.
        $replay = $this->withToken($token)->postJson('/api/v1/sync/reconcile', ['operations' => [$complete]]);
        $replay->assertJsonPath('outcomes.0.replay', true);
        $this->assertSame('completed', $task->refresh()->status);

        // A NEW operation_id completing an already-completed task is an applied no-op.
        $fresh = $this->withToken($token)->postJson('/api/v1/sync/reconcile', [
            'operations' => [$this->envelope('op-complete-2', 'task:status', $task->id, ['status' => 'completed'])],
        ]);
        $fresh->assertJsonPath('outcomes.0.status', 'applied');
        $this->assertSame('completed', $task->refresh()->status);
    }

    public function test_stale_task_update_conflicts_and_does_not_overwrite(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Original', 3);

        $update = $this->withToken($token)->postJson('/api/v1/sync/reconcile', [
            'operations' => [$this->envelope('op-update-1', 'task:update', $task->id, ['title' => 'Newer title'], 2)],
        ]);

        $update->assertStatus(200)
            ->assertJsonPath('outcomes.0.status', 'conflict')
            ->assertJsonPath('outcomes.0.code', 'VERSION_CONFLICT');
        $this->assertSame('Original', $task->refresh()->title);
        $this->assertTrue(in_array('op-update-1', $update->json('needs_review'), true));
    }

    public function test_stale_note_update_conflicts(): void
    {
        [$user, $token] = $this->userWithToken();
        $note = Note::query()->create(['user_id' => $user->id, 'title' => 'Note', 'document_json' => [], 'version' => 2]);

        $response = $this->withToken($token)->postJson('/api/v1/sync/reconcile', [
            'operations' => [$this->envelope('op-note-1', 'note:update', $note->id, ['title' => 'Changed', 'base_version' => 1])],
        ]);

        $response->assertJsonPath('outcomes.0.status', 'conflict')
            ->assertJsonPath('outcomes.0.code', 'VERSION_CONFLICT');
        $this->assertSame('Note', $note->refresh()->title);
    }

    public function test_ownership_isolation(): void
    {
        [$userA, $tokenA] = $this->userWithToken();
        [$userB, $tokenB] = $this->userWithToken();
        $taskA = $this->createTask($userA->id, 'A work');

        // User B cannot update user A's task through reconciliation.
        $response = $this->withToken($tokenB)->postJson('/api/v1/sync/reconcile', [
            'operations' => [$this->envelope('op-b-1', 'task:update', $taskA->id, ['title' => 'Hijack'], 1)],
        ]);

        $response->assertJsonPath('outcomes.0.status', 'rejected')
            ->assertJsonPath('outcomes.0.code', 'NOT_FOUND');
        $this->assertSame('A work', $taskA->refresh()->title);
    }

    public function test_unsupported_operation_type_is_rejected(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/sync/reconcile', [
            'operations' => [$this->envelope('op-billing-1', 'billing:charge', null, ['amount' => 1])],
        ])->assertStatus(422);
    }

    public function test_batch_is_bounded(): void
    {
        [$user, $token] = $this->userWithToken();
        $ops = [];
        for ($i = 0; $i < 51; $i++) {
            $ops[] = $this->envelope("op-batch-{$i}", 'task:create', null, ['title' => "Task {$i}"]);
        }

        $this->withToken($token)->postJson('/api/v1/sync/reconcile', ['operations' => $ops])->assertStatus(422);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_one_bad_operation_does_not_corrupt_the_batch(): void
    {
        [$user, $token] = $this->userWithToken();

        $response = $this->withToken($token)->postJson('/api/v1/sync/reconcile', [
            'operations' => [
                $this->envelope('op-bad-1', 'task:create', null, ['title' => '']),
                $this->envelope('op-good-1', 'task:create', null, ['title' => 'Good task']),
            ],
        ]);

        $response->assertStatus(200);
        $outcomes = $response->json('outcomes');
        $this->assertSame('rejected', $outcomes[0]['status']);
        $this->assertSame('applied', $outcomes[1]['status']);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_workspace_scoped_note_keeps_workspace_context(): void
    {
        [$user, $token] = $this->userWithToken();
        $workspace = Workspace::query()->create(['user_id' => $user->id, 'name' => 'Study', 'slug' => 'study']);

        $response = $this->withToken($token)->postJson('/api/v1/sync/reconcile', [
            'operations' => [$this->envelope('op-ws-1', 'note:create', null, ['title' => 'Class note', 'workspace_id' => $workspace->id])],
        ]);

        $response->assertJsonPath('outcomes.0.status', 'applied');
        $note = Note::query()->where('user_id', $user->id)->first();
        $this->assertSame($workspace->id, $note->workspace_id);
    }

    public function test_subtask_create_reconciles_under_the_task(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Parent');

        $response = $this->withToken($token)->postJson('/api/v1/sync/reconcile', [
            'operations' => [$this->envelope('op-sub-1', 'subtask:create', $task->id, ['title' => 'Sub'])],
        ]);

        $response->assertJsonPath('outcomes.0.status', 'applied');
        $this->assertDatabaseCount('subtasks', 1);
        $this->assertSame($task->id, Subtask::query()->first()->task_id);
    }

    public function test_online_path_accepts_x_operation_id_and_records_ledger(): void
    {
        [$user, $token] = $this->userWithToken();

        $first = $this->withToken($token)->postJson('/api/v1/tasks', ['title' => 'Online task'], ['X-Operation-Id' => 'op-online-1']);
        $first->assertStatus(201)->assertJsonPath('task.title', 'Online task');

        // Retry the SAME operation (simulating response-loss) → replay, no duplicate.
        $retry = $this->withToken($token)->postJson('/api/v1/tasks', ['title' => 'Online task'], ['X-Operation-Id' => 'op-online-1']);
        $retry->assertStatus(201)->assertJsonPath('task.title', 'Online task');

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_online_task_update_base_version_conflict_returns_409(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'V', 4);

        $this->withToken($token)->putJson("/api/v1/tasks/{$task->id}", [
            'title' => 'New',
            'base_version' => 2,
        ], ['X-Operation-Id' => 'op-upd-online-1'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'VERSION_CONFLICT');
        $this->assertSame('V', $task->refresh()->title);
    }

    public function test_ledger_retention_prunes_old_rows(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->withToken($token)->postJson('/api/v1/sync/reconcile', [
            'operations' => [$this->envelope('op-old-1', 'task:create', null, ['title' => 'Old task'])],
        ])->assertStatus(200);

        DB::table('offline_operations')
            ->where('operation_id', 'op-old-1')
            ->update(['created_at' => now()->subDays(120)]);

        $this->artisan('offline:prune-ledger')->assertSuccessful();

        $this->assertDatabaseMissing('offline_operations', ['operation_id' => 'op-old-1']);
        $this->assertDatabaseCount('tasks', 1);
    }
}
