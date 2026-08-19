<?php

namespace Tests\Feature\Api;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoalBreakdownProposalApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithGoal(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $goal = Goal::query()->create([
            'user_id' => $user->id,
            'title' => 'Ship product',
            'horizon' => 'quarterly',
            'status' => 'active',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        return [$user, $token, $goal];
    }

    private function fakeOllamaGoalBreakdown(int $goalId, array $milestones = []): void
    {
        config([
            'ai.driver' => 'ollama',
            'ai.ollama.base_url' => 'http://localhost:11434',
            'ai.ollama.model' => 'llama3.1',
        ]);

        $milestones = $milestones ?: [
            ['title' => 'Research', 'target_date' => '2026-09-01', 'estimated_minutes' => 600],
            ['title' => 'Build', 'estimated_minutes' => 1800],
        ];

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

    public function test_breakdown_requires_authentication_and_owned_goal(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $other = User::factory()->create();
        $foreign = Goal::query()->create([
            'user_id' => $other->id,
            'title' => 'Foreign',
            'horizon' => 'quarterly',
            'status' => 'active',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $this->postJson("/api/v1/goals/{$goal->id}/breakdown-proposals", [])->assertStatus(401);

        $this->withToken($token)->postJson("/api/v1/goals/{$foreign->id}/breakdown-proposals", [])
            ->assertStatus(404);
    }

    public function test_breakdown_generates_a_pending_proposal(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $this->fakeOllamaGoalBreakdown($goal->id);

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/breakdown-proposals", [
            'instructions' => 'Break this down into two phases',
        ])->assertStatus(200)
            ->assertJsonPath('proposal.proposal_type', 'goal_breakdown')
            ->assertJsonPath('proposal.decision', 'pending')
            ->assertJsonPath('proposal.schema_version', 1)
            ->assertJsonPath('proposal.payload.goal_id', $goal->id)
            ->assertJsonCount(2, 'proposal.payload.milestones');

        $this->assertDatabaseHas('ai_proposals', [
            'user_id' => $user->id,
            'proposal_type' => 'goal_breakdown',
            'decision' => 'pending',
        ]);

        // No hierarchy is committed before approval (FR-52 postcondition).
        $this->assertDatabaseCount('milestones', 0);
        $this->assertDatabaseHas('ai_runs', [
            'user_id' => $user->id,
            'proposal_type' => 'goal_breakdown',
            'status' => 'success',
        ]);
    }

    public function test_breakdown_rejects_mismatched_goal_id(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $this->fakeOllamaGoalBreakdown($goal->id + 100);

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/breakdown-proposals", [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'AI_OUTPUT_INVALID');

        $this->assertDatabaseCount('ai_proposals', 0);
    }

    public function test_accept_applies_milestones_within_transaction(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $this->fakeOllamaGoalBreakdown($goal->id);

        $response = $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/breakdown-proposals", [])
            ->assertStatus(200);
        $proposalId = $response->json('proposal.id');

        $this->withToken($token)->postJson("/api/v1/ai/proposals/{$proposalId}/accept")
            ->assertStatus(200)
            ->assertJsonCount(2, 'milestones');

        $this->assertDatabaseCount('milestones', 2);
        $this->assertDatabaseHas('milestones', [
            'goal_id' => $goal->id,
            'user_id' => $user->id,
            'title' => 'Research',
            'target_date' => '2026-09-01',
            'estimated_minutes' => 600,
        ]);
        $this->assertDatabaseHas('milestones', [
            'goal_id' => $goal->id,
            'title' => 'Build',
            'estimated_minutes' => 1800,
        ]);

        $this->assertDatabaseHas('ai_proposals', [
            'id' => $proposalId,
            'decision' => 'accepted',
        ]);
    }

    public function test_reject_creates_no_domain_mutation(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $this->fakeOllamaGoalBreakdown($goal->id);

        $response = $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/breakdown-proposals", [])
            ->assertStatus(200);
        $proposalId = $response->json('proposal.id');

        $this->withToken($token)->postJson("/api/v1/ai/proposals/{$proposalId}/reject")
            ->assertStatus(200)
            ->assertJsonPath('proposal.decision', 'rejected');

        $this->assertDatabaseCount('milestones', 0);
        $this->assertDatabaseHas('ai_proposals', [
            'id' => $proposalId,
            'decision' => 'rejected',
        ]);
    }

    public function test_accepting_a_non_pending_proposal_is_rejected(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $this->fakeOllamaGoalBreakdown($goal->id);

        $response = $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/breakdown-proposals", [])
            ->assertStatus(200);
        $proposalId = $response->json('proposal.id');

        $this->withToken($token)->postJson("/api/v1/ai/proposals/{$proposalId}/reject")->assertStatus(200);
        $this->withToken($token)->postJson("/api/v1/ai/proposals/{$proposalId}/accept")
            ->assertStatus(422);
    }

    public function test_proposals_are_owner_scoped(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $this->fakeOllamaGoalBreakdown($goal->id);

        $response = $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/breakdown-proposals", [])
            ->assertStatus(200);
        $proposalId = $response->json('proposal.id');

        $other = User::factory()->create();
        $otherToken = $other->createToken('owner')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->getJson("/api/v1/ai/proposals/{$proposalId}")
            ->assertStatus(404);
        $this->withToken($otherToken)->postJson("/api/v1/ai/proposals/{$proposalId}/accept")
            ->assertStatus(404);
    }

    public function test_proposals_can_be_listed_and_filtered(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $this->fakeOllamaGoalBreakdown($goal->id);

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/breakdown-proposals", [])
            ->assertStatus(200);

        $this->withToken($token)->getJson('/api/v1/ai/proposals')
            ->assertStatus(200)
            ->assertJsonCount(1, 'proposals');

        $this->withToken($token)->getJson('/api/v1/ai/proposals?decision=pending')
            ->assertStatus(200)
            ->assertJsonCount(1, 'proposals');

        $this->withToken($token)->getJson('/api/v1/ai/proposals?decision=accepted')
            ->assertStatus(200)
            ->assertJsonCount(0, 'proposals');
    }

    public function test_generic_proposal_endpoint_persists_and_can_be_viewed(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();

        config([
            'ai.driver' => 'ollama',
            'ai.ollama.base_url' => 'http://localhost:11434',
        ]);

        Http::fake([
            'http://localhost:11434/api/generate' => Http::response([
                'response' => json_encode([
                    'type' => 'milestone_proposal',
                    'goal_id' => 1,
                    'title' => 'Milestone A',
                    'estimated_minutes' => 300,
                ]),
            ], 200),
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/ai/proposals', [
            'type' => 'milestone',
            'prompt' => 'Propose a milestone',
        ])->assertStatus(200)
            ->assertJsonPath('proposal.proposal_type', 'milestone')
            ->assertJsonPath('proposal.decision', 'pending');

        $proposalId = $response->json('proposal.id');

        $this->withToken($token)->getJson("/api/v1/ai/proposals/{$proposalId}")
            ->assertStatus(200)
            ->assertJsonPath('proposal.proposal_type', 'milestone');
    }
}
