<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-P19-004 + P19-003 + P19-033 — workspace control plane, default
 * provisioning, backfill semantics, ownership isolation and archive safety.
 */
class WorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    /** Existing-data fixtures created through the domain API. */
    private function createFixtureData(int $userId, string $token): array
    {
        $goalId = $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => 'Backfill goal', 'horizon' => 'yearly', 'target_date' => '2026-12-31',
        ])->json('goal.id');
        $taskId = $this->withToken($token)->postJson('/api/v1/tasks', [
            'title' => 'Backfill task', 'priority_tier' => 2, 'estimated_minutes' => 30,
        ])->json('task.id');
        $programId = $this->withToken($token)->postJson('/api/v1/programs', [
            'name' => 'Backfill program', 'workload_type' => 'structured', 'weekly_target_minutes' => 120,
        ])->json('program.id');
        $noteId = Note::factory()->create(['user_id' => $userId])->id;

        return ['goal' => (int) $goalId, 'task' => (int) $taskId, 'program' => (int) $programId, 'note' => (int) $noteId];
    }

    public function test_workspaces_require_authentication(): void
    {
        $this->getJson('/api/v1/workspaces')->assertStatus(401);
        $this->postJson('/api/v1/workspaces', ['name' => 'X'])->assertStatus(401);
    }

    public function test_a_default_personal_workspace_is_provisioned_on_first_read(): void
    {
        [$user, $token] = $this->userWithToken();

        $response = $this->withToken($token)->getJson('/api/v1/workspaces')->assertOk();
        $response->assertJsonCount(1, 'workspaces')
            ->assertJsonPath('workspaces.0.name', 'Personal')
            ->assertJsonPath('workspaces.0.slug', 'personal')
            ->assertJsonPath('workspaces.0.is_default', true)
            ->assertJsonPath('workspaces.0.status', 'active');
        // Exactly one default per user.
        $second = $this->withToken($token)->getJson('/api/v1/workspaces');
        $second->assertJsonCount(1, 'workspaces');

        $this->assertDatabaseHas('workspaces', [
            'user_id' => $user->id,
            'name' => 'Personal',
            'is_default' => true,
        ]);
    }

    public function test_existing_data_is_backfilled_into_the_default_workspace(): void
    {
        [$user, $token] = $this->userWithToken();

        $fixtures = $this->createFixtureData($user->id, $token);
        $defaultId = (int) ($this->withToken($token)->getJson('/api/v1/workspaces')->json('default_workspace_id'));

        foreach (['goals' => $fixtures['goal'], 'tasks' => $fixtures['task'], 'programs' => $fixtures['program'], 'notes' => $fixtures['note']] as $table => $id) {
            $this->assertDatabaseHas($table, ['id' => $id, 'workspace_id' => $defaultId]);
        }
    }

    public function test_backfill_is_idempotent_and_never_touches_assigned_rows(): void
    {
        [$user, $token] = $this->userWithToken();
        $goalId = (int) $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => 'Idempotent goal', 'horizon' => 'yearly', 'target_date' => '2026-12-31',
        ])->json('goal.id');

        $first = (int) $this->withToken($token)->getJson('/api/v1/workspaces')->json('default_workspace_id');
        $this->assertDatabaseHas('goals', ['id' => $goalId, 'workspace_id' => $first]);

        // Re-running the ensure path must not duplicate the default or
        // reassign rows that already have a workspace.
        $again = $this->withToken($token)->getJson('/api/v1/workspaces');
        $this->assertCount(1, $again->json('workspaces'));
        $this->assertSame($first, $again->json('default_workspace_id'));
        $this->assertDatabaseHas('goals', ['id' => $goalId, 'workspace_id' => $first]);
    }

    public function test_workspaces_can_be_created_listed_and_owner_scoped(): void
    {
        [$user, $token] = $this->userWithToken();
        [$otherUser, $otherToken] = $this->userWithToken();

        $created = $this->withToken($token)->postJson('/api/v1/workspaces', [
            'name' => 'Research',
            'description' => 'Deep work on research topics',
            'type' => 'research',
            'accent' => '#4f46e5',
        ])->assertStatus(201)
            ->assertJsonPath('workspace.name', 'Research')
            ->assertJsonPath('workspace.slug', 'research')
            ->assertJsonPath('workspace.type', 'research')
            ->assertJsonPath('workspace.is_default', false)
            ->json('workspace.id');

        // Owner sees both; other users see only their own.
        $mine = $this->withToken($token)->getJson('/api/v1/workspaces')->assertOk();
        $this->assertCount(2, $mine->json('workspaces'));

        // Sanctum caches the resolved user across in-test requests; reset
        // before switching tokens (project convention, TASK-014 notes).
        $this->app['auth']->forgetGuards();
        $theirs = $this->withToken($otherToken)->getJson('/api/v1/workspaces')->assertOk();
        $this->assertCount(1, $theirs->json('workspaces'));
        $this->assertSame('Personal', $theirs->json('workspaces.0.name'));

        // Cross-user reads are 404, never leaks.
        $this->withToken($otherToken)->getJson("/api/v1/workspaces/{$created}")->assertStatus(404);
        // Cross-user mutation is impossible too.
        $this->withToken($otherToken)->patchJson("/api/v1/workspaces/{$created}", ['name' => 'Hijacked'])->assertStatus(404);
    }

    public function test_duplicate_names_get_unique_slugs_and_validation_rejects_empty_name(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => 'Research'])->assertStatus(201);
        $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => 'Research'])
            ->assertStatus(201)
            ->assertJsonPath('workspace.slug', 'research-2');

        $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => '   '])->assertStatus(422);
    }

    public function test_patch_updates_only_sent_fields(): void
    {
        [$user, $token] = $this->userWithToken();
        $id = (int) $this->withToken($token)->postJson('/api/v1/workspaces', [
            'name' => 'Alpha',
            'description' => 'keep me',
            'icon' => 'star',
        ])->json('workspace.id');

        $this->withToken($token)->patchJson("/api/v1/workspaces/{$id}", ['accent' => '#22c55e'])
            ->assertOk()
            ->assertJsonPath('workspace.description', 'keep me')
            ->assertJsonPath('workspace.icon', 'star')
            ->assertJsonPath('workspace.accent', '#22c55e');

        // Explicit null clears.
        $this->withToken($token)->patchJson("/api/v1/workspaces/{$id}", ['icon' => null])
            ->assertOk()
            ->assertJsonPath('workspace.icon', null);
    }

    public function test_archive_preserves_data_hides_from_active_switcher_and_restore_recovers(): void
    {
        [$user, $token] = $this->userWithToken();
        $fixtures = $this->createFixtureData($user->id, $token);
        $defaultId = (int) $this->withToken($token)->getJson('/api/v1/workspaces')->json('default_workspace_id');
        $id = (int) $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => 'Side Quests'])->json('workspace.id');

        $this->withToken($token)->delete("/api/v1/workspaces/{$id}/archive")
            ->assertOk()
            ->assertJsonPath('workspace.status', 'archived');

        // Hidden from the active switcher…
        $list = $this->withToken($token)->getJson('/api/v1/workspaces');
        $this->assertCount(1, $list->json('workspaces'));
        // …but visible when explicitly requested and data preserved.
        $withArchived = $this->withToken($token)->getJson('/api/v1/workspaces?include_archived=1');
        $this->assertCount(2, $withArchived->json('workspaces'));
        $this->assertDatabaseHas('goals', ['id' => $fixtures['goal'], 'workspace_id' => $defaultId]);

        // Archived workspace cannot become default; restore recovers it.
        $this->withToken($token)->postJson("/api/v1/workspaces/{$id}/default")->assertStatus(422);
        $this->withToken($token)->postJson("/api/v1/workspaces/{$id}/restore")
            ->assertOk()
            ->assertJsonPath('workspace.status', 'active');
        $this->assertCount(2, $this->withToken($token)->getJson('/api/v1/workspaces')->json('workspaces'));
    }

    public function test_the_default_workspace_cannot_be_archived(): void
    {
        [, $token] = $this->userWithToken();
        $defaultId = (int) $this->withToken($token)->getJson('/api/v1/workspaces')->json('default_workspace_id');

        $this->withToken($token)->delete("/api/v1/workspaces/{$defaultId}/archive")->assertStatus(422);
    }

    public function test_setting_a_new_default_moves_exactly_one_flag(): void
    {
        [$user, $token] = $this->userWithToken();
        $oldDefault = (int) $this->withToken($token)->getJson('/api/v1/workspaces')->json('default_workspace_id');
        $newDefault = (int) $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => 'Work'])->json('workspace.id');

        $this->withToken($token)->postJson("/api/v1/workspaces/{$newDefault}/default")
            ->assertOk()
            ->assertJsonPath('workspace.is_default', true);

        $this->assertDatabaseHas('workspaces', ['id' => $newDefault, 'is_default' => true]);
        $this->assertDatabaseHas('workspaces', ['id' => $oldDefault, 'is_default' => false]);
        $this->assertEquals(
            $newDefault,
            $this->withToken($token)->getJson('/api/v1/workspaces')->json('default_workspace_id'),
        );
    }
}
