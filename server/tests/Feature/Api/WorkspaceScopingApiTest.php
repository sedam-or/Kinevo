<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-P19-011..017 / P19-024 — server-side workspace scoping contract:
 * explicit context wins, absent context falls back to the owner's default
 * workspace, lists filter by declared active workspace, and consistency is
 * validated server-side.
 */
class WorkspaceScopingApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/workspaces'); // provision Personal

        return [$user, $token];
    }

    private function createWorkspace(string $token, string $name): int
    {
        return (int) $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => $name])->json('workspace.id');
    }

    public function test_goal_without_explicit_workspace_lands_in_the_default(): void
    {
        [, $token] = $this->userWithToken();

        $goal = $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => 'Default scoped goal', 'horizon' => 'yearly',
        ])->json('goal');

        $defaultId = (int) $this->withToken($token)->getJson('/api/v1/workspaces')->json('default_workspace_id');
        $this->assertSame($defaultId, $goal['workspace_id']);
    }

    public function test_goals_filter_by_declared_workspace_and_support_global_view(): void
    {
        [$user, $token] = $this->userWithToken();
        $defaultId = (int) $this->withToken($token)->getJson('/api/v1/workspaces')->json('default_workspace_id');
        $researchId = $this->createWorkspace($token, 'Research');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => 'Personal goal', 'horizon' => 'yearly',
        ]);
        $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => 'Research goal', 'horizon' => 'yearly', 'workspace_id' => $researchId,
        ]);

        // Active-workspace view…
        $research = $this->withToken($token)->getJson("/api/v1/goals?workspace_id={$researchId}");
        $this->assertCount(1, $research->json('goals'));
        $this->assertSame('Research goal', $research->json('goals.0.title'));

        $personal = $this->withToken($token)->getJson("/api/v1/goals?workspace_id={$defaultId}");
        $this->assertCount(1, $personal->json('goals'));

        // …and the explicit global view shows both.
        $global = $this->withToken($token)->getJson('/api/v1/goals?workspace=all');
        $this->assertCount(2, $global->json('goals'));

        // Foreign workspace id is 404, never a leak.
        $other = User::factory()->create();
        $otherToken = $other->createToken('x')->plainTextToken;
        // Reset before switching tokens or the cached guard resolves the
        // wrong user (project convention, TASK-014 notes).
        $this->app['auth']->forgetGuards();
        $foreign = (int) $this->withToken($otherToken)->getJson('/api/v1/workspaces')->json('default_workspace_id');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/v1/goals?workspace_id={$foreign}")->assertStatus(404);
    }

    public function test_task_inherits_the_linked_goal_workspace_and_rejects_conflicts(): void
    {
        [$user, $token] = $this->userWithToken();
        $researchId = $this->createWorkspace($token, 'Research');
        $this->app['auth']->forgetGuards();

        $goalId = (int) $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => 'Research goal', 'horizon' => 'quarterly', 'workspace_id' => $researchId,
        ])->json('goal.id');

        // Inheritance: no explicit workspace on the task → goal's workspace.
        $task = $this->withToken($token)->postJson('/api/v1/tasks', [
            'title' => 'Inheriting task', 'goal_id' => $goalId,
        ])->json('task');
        $this->assertSame($researchId, $task['workspace_id']);

        // Conflict: explicit different workspace alongside the goal → 422.
        $defaultId = (int) $this->withToken($token)->getJson('/api/v1/workspaces')->json('default_workspace_id');
        $this->withToken($token)->postJson('/api/v1/tasks', [
            'title' => 'Conflicting task', 'goal_id' => $goalId, 'workspace_id' => $defaultId,
        ])->assertStatus(422);
    }

    public function test_archived_workspace_rejects_new_scoped_work(): void
    {
        [$user, $token] = $this->userWithToken();
        $sideId = $this->createWorkspace($token, 'Side Quests');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->delete("/api/v1/workspaces/{$sideId}/archive")->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => 'Blocked goal', 'horizon' => 'yearly', 'workspace_id' => $sideId,
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/notes', [
            'title' => 'Blocked note', 'workspace_id' => $sideId,
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/canvases', [
            'title' => 'Blocked canvas', 'workspace_id' => $sideId,
        ])->assertStatus(422);
    }

    public function test_quick_capture_scopes_to_the_declared_workspace(): void
    {
        [$user, $token] = $this->userWithToken();
        $researchId = $this->createWorkspace($token, 'Research');
        $this->app['auth']->forgetGuards();

        // Placed capture in Research.
        $placed = $this->withToken($token)->postJson('/api/v1/quick-capture', [
            'title' => 'Research capture', 'workspace_id' => $researchId,
        ])->json('task.workspace_id');
        $this->assertSame($researchId, $placed);

        // No capacity path still carries the workspace (never disappears).
        for ($i = 0; $i < 12; $i++) {
            $this->withToken($token)->postJson('/api/v1/quick-capture', [
                'title' => "Filler {$i}", 'duration_minutes' => 600,
            ]);
        }
        $this->app['auth']->forgetGuards();
        $unplaced = $this->withToken($token)->postJson('/api/v1/quick-capture', [
            'title' => 'Overflow capture', 'duration_minutes' => 600, 'workspace_id' => $researchId,
        ]);
        $payload = $unplaced->json();
        if (array_key_exists('task', $payload)) {
            $this->assertSame($researchId, $payload['task']['workspace_id']);
        }
    }

    public function test_notes_and_canvases_and_programs_scope_and_filter(): void
    {
        [$user, $token] = $this->userWithToken();
        $defaultId = (int) $this->withToken($token)->getJson('/api/v1/workspaces')->json('default_workspace_id');
        $researchId = $this->createWorkspace($token, 'Research');
        $this->app['auth']->forgetGuards();

        $note = $this->withToken($token)->postJson('/api/v1/notes', [
            'title' => 'Research note', 'workspace_id' => $researchId,
        ])->json('note');
        $this->assertSame($researchId, $note['workspace_id']);

        $program = $this->withToken($token)->postJson('/api/v1/programs', [
            'name' => 'Research program', 'workload_type' => 'structured',
            'weekly_target_minutes' => 90, 'workspace_id' => $researchId,
        ])->json('program');
        $this->assertSame($researchId, $program['workspace_id']);

        // One program in the default workspace too.
        $this->withToken($token)->postJson('/api/v1/programs', [
            'name' => 'Personal program', 'workload_type' => 'structured',
            'weekly_target_minutes' => 60,
        ]);

        $canvas = $this->withToken($token)->postJson('/api/v1/canvases', [
            'title' => 'Research canvas', 'workspace_id' => $researchId,
        ])->json('canvas');
        $this->assertSame($researchId, $canvas['workspace_id']);

        // Filters per surface.
        $this->assertCount(1, $this->withToken($token)->getJson("/api/v1/notes?workspace_id={$researchId}")->json('notes'));
        $this->assertCount(1, $this->withToken($token)->getJson("/api/v1/programs?workspace_id={$researchId}")->json('programs'));
        $this->assertCount(1, $this->withToken($token)->getJson("/api/v1/canvases?workspace_id={$researchId}")->json('canvases'));
        $this->assertCount(1, $this->withToken($token)->getJson("/api/v1/programs?workspace_id={$defaultId}")->json('programs'));
    }
}
