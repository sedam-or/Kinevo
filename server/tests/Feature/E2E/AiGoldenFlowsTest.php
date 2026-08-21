<?php

namespace Tests\Feature\E2E;

use App\Models\Goal;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TASK-155 — AI Golden Flows.
 *
 * Walks the AI-assisted golden flows end to end:
 *   Goal → AI breakdown proposal → Preview → Accept → Milestones
 *   Note → Task extraction → Preview → Accept → Tasks
 *   AI unavailable → core app still works
 * Plus: malformed AI JSON, cross-user proposal, stale proposal, rejected proposal.
 *
 * Every assertion targets user-visible API responses — the payloads the UI
 * renders from — and confirms the resulting domain objects appear in the
 * public list/index endpoints. Database-only assertions are deliberately not
 * used for the user-visible outcomes.
 */
final class AiGoldenFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function fakeOllamaGoalBreakdown(int $goalId, array $milestones): void
    {
        config([
            'ai.driver' => 'ollama',
            'ai.ollama.base_url' => 'http://localhost:11434',
            'ai.ollama.model' => 'llama3.1',
        ]);

        Http::fake([
            'http://localhost:11434/api/generate' => Http::response([
                'response' => json_encode([
                    'type' => 'goal_breakdown_proposal',
                    'goal_id' => $goalId,
                    'milestones' => $milestones,
                ]),
            ], 200),
        ]);
    }

    private function fakeOllamaTaskExtraction(array $tasks): void
    {
        config([
            'ai.driver' => 'ollama',
            'ai.ollama.base_url' => 'http://localhost:11434',
            'ai.ollama.model' => 'llama3.1',
        ]);

        Http::fake([
            'http://localhost:11434/api/generate' => Http::response([
                'response' => json_encode([
                    'type' => 'task_extraction_proposal',
                    'tasks' => $tasks,
                ]),
            ], 200),
        ]);
    }

    public function test_goal_breakdown_golden_flow(): void
    {
        [$user, $token] = $this->userWithToken();
        $api = $this->withToken($token);

        $goal = $api->postJson('/api/v1/goals', [
            'title' => 'Ship product',
            'horizon' => 'quarterly',
            'target_date' => '2026-12-31',
        ])->assertStatus(201)
            ->json('goal');

        $this->fakeOllamaGoalBreakdown($goal['id'], [
            ['title' => 'Research', 'target_date' => '2026-09-01', 'estimated_minutes' => 600],
            ['title' => 'Build', 'estimated_minutes' => 1800],
        ]);

        // ── AI breakdown proposal ─────────────────────────────────────────
        $proposal = $api->postJson("/api/v1/goals/{$goal['id']}/breakdown-proposals", [
            'instructions' => 'Break this down into phases',
        ])->assertStatus(200)
            ->assertJsonPath('proposal.proposal_type', 'goal_breakdown')
            ->assertJsonPath('proposal.decision', 'pending')
            ->assertJsonCount(2, 'proposal.payload.milestones')
            ->json('proposal');

        // ── Preview ───────────────────────────────────────────────────────
        $api->getJson("/api/v1/ai/proposals/{$proposal['id']}")
            ->assertStatus(200)
            ->assertJsonPath('proposal.proposal_type', 'goal_breakdown')
            ->assertJsonPath('proposal.decision', 'pending');

        // ── Accept → Milestones (user-visible in the goal's milestone list) ─
        $api->postJson("/api/v1/ai/proposals/{$proposal['id']}/accept")
            ->assertStatus(200)
            ->assertJsonCount(2, 'milestones');

        $api->getJson("/api/v1/goals/{$goal['id']}/milestones")
            ->assertStatus(200)
            ->assertJsonCount(2, 'milestones')
            ->assertJsonPath('milestones.0.title', 'Research')
            ->assertJsonPath('milestones.1.title', 'Build');
    }

    public function test_note_task_extraction_golden_flow(): void
    {
        [$user, $token] = $this->userWithToken();
        $api = $this->withToken($token);

        $note = $api->postJson('/api/v1/notes', [
            'title' => 'Errands',
            'plain_text_cache' => 'Buy milk, call dentist, write report.',
        ])->assertStatus(201)
            ->json('note');

        $this->fakeOllamaTaskExtraction([
            ['title' => 'Buy milk', 'estimated_minutes' => 10],
            ['title' => 'Write report', 'due_at' => '2026-08-20'],
        ]);

        // ── Task extraction proposal ──────────────────────────────────────
        $proposal = $api->postJson('/api/v1/ai/extract-tasks', [
            'note_id' => $note['id'],
        ])->assertStatus(200)
            ->assertJsonPath('proposal.proposal_type', 'task_extraction')
            ->assertJsonPath('proposal.decision', 'pending')
            ->assertJsonCount(2, 'proposal.payload.tasks')
            ->json('proposal');

        // ── Preview ───────────────────────────────────────────────────────
        $api->getJson("/api/v1/ai/proposals/{$proposal['id']}")
            ->assertStatus(200)
            ->assertJsonPath('proposal.proposal_type', 'task_extraction')
            ->assertJsonPath('proposal.decision', 'pending');

        // ── Accept → Tasks (user-visible in the task list) ────────────────
        $api->postJson("/api/v1/ai/proposals/{$proposal['id']}/accept")
            ->assertStatus(200)
            ->assertJsonCount(2, 'tasks');

        $api->getJson('/api/v1/tasks')
            ->assertStatus(200)
            ->assertJsonCount(2, 'tasks')
            ->assertJsonPath('tasks.0.title', 'Buy milk')
            ->assertJsonPath('tasks.1.title', 'Write report');
    }

    public function test_ai_unavailable_core_app_still_works(): void
    {
        config(['ai.driver' => 'disabled']);
        [$user, $token] = $this->userWithToken();
        $api = $this->withToken($token);

        // Core app keeps working without AI.
        $goal = $api->postJson('/api/v1/goals', [
            'title' => 'Ship product',
            'horizon' => 'quarterly',
            'target_date' => '2026-12-31',
        ])->assertStatus(201)
            ->json('goal');

        $api->postJson('/api/v1/tasks', [
            'title' => 'Manual task',
            'goal_id' => $goal['id'],
        ])->assertStatus(201);

        // AI surface reports unavailable and degrades gracefully.
        $api->getJson('/api/v1/ai/status')
            ->assertStatus(200)
            ->assertJsonPath('status.available', false);

        $api->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction',
            'prompt' => 'Anything',
        ])->assertStatus(503)
            ->assertJsonPath('code', 'AI_PROVIDER_UNAVAILABLE');

        $api->postJson("/api/v1/goals/{$goal['id']}/breakdown-proposals", [])
            ->assertStatus(503)
            ->assertJsonPath('code', 'AI_PROVIDER_UNAVAILABLE');
    }

    public function test_malformed_ai_json_is_rejected(): void
    {
        [$user, $token] = $this->userWithToken();
        $api = $this->withToken($token);

        $goal = Goal::query()->create([
            'user_id' => $user->id,
            'title' => 'Ship product',
            'horizon' => 'quarterly',
            'status' => 'active',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        config([
            'ai.driver' => 'ollama',
            'ai.ollama.base_url' => 'http://localhost:11434',
            'ai.ollama.model' => 'llama3.1',
        ]);
        Http::fake([
            'http://localhost:11434/api/generate' => Http::response(['response' => '%%% not json'], 200),
        ]);

        $api->postJson("/api/v1/goals/{$goal->id}/breakdown-proposals", [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'AI_OUTPUT_INVALID');

        $this->assertDatabaseCount('ai_proposals', 0);
        $this->assertDatabaseCount('milestones', 0);
    }

    public function test_cross_user_proposal_is_rejected(): void
    {
        [$user, $token] = $this->userWithToken();
        $api = $this->withToken($token);

        $goal = $api->postJson('/api/v1/goals', [
            'title' => 'Ship product',
            'horizon' => 'quarterly',
            'target_date' => '2026-12-31',
        ])->assertStatus(201)
            ->json('goal');

        $this->fakeOllamaGoalBreakdown($goal['id'], [
            ['title' => 'Research', 'estimated_minutes' => 600],
            ['title' => 'Build', 'estimated_minutes' => 1800],
        ]);

        $proposalId = $api->postJson("/api/v1/goals/{$goal['id']}/breakdown-proposals", [])
            ->assertStatus(200)
            ->json('proposal.id');

        // A different owner cannot see or apply the proposal.
        $other = User::factory()->create();
        $otherToken = $other->createToken('owner')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->getJson("/api/v1/ai/proposals/{$proposalId}")
            ->assertStatus(404);
        $this->withToken($otherToken)->postJson("/api/v1/ai/proposals/{$proposalId}/accept")
            ->assertStatus(404);

        $this->assertDatabaseCount('milestones', 0);
    }

    public function test_stale_proposal_is_rejected(): void
    {
        [$user, $token] = $this->userWithToken();
        $api = $this->withToken($token);

        $goal = $api->postJson('/api/v1/goals', [
            'title' => 'Ship product',
            'horizon' => 'quarterly',
            'target_date' => '2026-12-31',
        ])->assertStatus(201)
            ->json('goal');

        $this->fakeOllamaGoalBreakdown($goal['id'], [
            ['title' => 'Research', 'estimated_minutes' => 600],
            ['title' => 'Build', 'estimated_minutes' => 1800],
        ]);

        $proposalId = $api->postJson("/api/v1/goals/{$goal['id']}/breakdown-proposals", [])
            ->assertStatus(200)
            ->json('proposal.id');

        // Reject first, then a stale accept on the now non-pending proposal.
        $api->postJson("/api/v1/ai/proposals/{$proposalId}/reject")->assertStatus(200);
        $api->postJson("/api/v1/ai/proposals/{$proposalId}/accept")
            ->assertStatus(422);

        $this->assertDatabaseCount('milestones', 0);
    }

    public function test_rejected_proposal_creates_no_mutation(): void
    {
        [$user, $token] = $this->userWithToken();
        $api = $this->withToken($token);

        $goal = $api->postJson('/api/v1/goals', [
            'title' => 'Ship product',
            'horizon' => 'quarterly',
            'target_date' => '2026-12-31',
        ])->assertStatus(201)
            ->json('goal');

        $this->fakeOllamaGoalBreakdown($goal['id'], [
            ['title' => 'Research', 'estimated_minutes' => 600],
            ['title' => 'Build', 'estimated_minutes' => 1800],
        ]);

        $proposalId = $api->postJson("/api/v1/goals/{$goal['id']}/breakdown-proposals", [])
            ->assertStatus(200)
            ->json('proposal.id');

        $api->postJson("/api/v1/ai/proposals/{$proposalId}/reject")
            ->assertStatus(200)
            ->assertJsonPath('proposal.decision', 'rejected');

        $this->assertDatabaseCount('milestones', 0);
        $this->assertDatabaseCount('tasks', 0);
    }
}
