<?php
namespace Tests\Feature\Api;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TASK-P17-004: review → EDIT → accept on goal breakdown proposals.
 * Editing revalidates through the same schema rules as AI output (FR-61) and
 * keeps the approval gate: nothing reaches milestones until acceptance (FR-62).
 */
class AiProposalEditApiTest extends TestCase
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

    private function seedPendingProposal(int $goalId, string $token): int
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
                    'rationale' => 'Research before build reduces rework.',
                    'risks' => ['Scope creep around integrations.'],
                    'milestones' => [
                        ['title' => 'Research', 'target_date' => '2026-09-01', 'estimated_minutes' => 600],
                        ['title' => 'Build', 'estimated_minutes' => 1800],
                    ],
                ]),
            ], 200),
        ]);
        $response = $this->withToken($token)
            ->postJson("/api/v1/goals/{$goalId}/breakdown-proposals");
        $response->assertStatus(200);

        return $response->json('proposal.id');
    }

    public function test_edit_updates_pending_proposal_and_marks_it_edited(): void
    {
        [, $token, $goal] = $this->userWithGoal();
        $id = $this->seedPendingProposal($goal->id, $token);

        $response = $this->withToken($token)->putJson("/api/v1/ai/proposals/{$id}", [
            'type' => 'goal_breakdown_proposal',
            'goal_id' => $goal->id,
            'rationale' => 'User adjusted plan.',
            'milestones' => [
                ['title' => 'Discovery', 'target_date' => '2026-09-05', 'estimated_minutes' => 300],
                ['title' => 'Build MVP', 'estimated_minutes' => 1200],
            ],
        ]);

        $response->assertStatus(200);
        $body = $response->json('proposal');
        $this->assertSame('edited', $body['decision']);
        $this->assertSame('Discovery', $body['payload']['milestones'][0]['title']);
        $this->assertSame('User adjusted plan.', $body['payload']['rationale']);
        $this->assertDatabaseHas('ai_proposals', ['id' => $id, 'decision' => 'edited']);
    }

    public function test_edit_revalidates_payload_against_schema(): void
    {
        [, $token, $goal] = $this->userWithGoal();
        $id = $this->seedPendingProposal($goal->id, $token);

        $this->withToken($token)->putJson("/api/v1/ai/proposals/{$id}", [
            'type' => 'goal_breakdown_proposal',
            'goal_id' => $goal->id,
            'milestones' => [
                ['target_date' => '2026-09-01'],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseHas('ai_proposals', ['id' => $id, 'decision' => 'pending']);
    }

    public function test_edit_cannot_retarget_the_goal(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $other = Goal::query()->create([
            'user_id' => $user->id,
            'title' => 'Other goal',
            'horizon' => 'quarterly',
            'status' => 'active',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);
        $id = $this->seedPendingProposal($goal->id, $token);

        $this->withToken($token)->putJson("/api/v1/ai/proposals/{$id}", [
            'type' => 'goal_breakdown_proposal',
            'goal_id' => $other->id,
            'milestones' => [['title' => 'X']],
        ])->assertStatus(422);
    }

    public function test_edit_is_owner_scoped(): void
    {
        [, $token, $goal] = $this->userWithGoal();
        $id = $this->seedPendingProposal($goal->id, $token);
        $stranger = User::factory()->create();
        $strangerToken = $stranger->createToken('s')->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($strangerToken)->putJson("/api/v1/ai/proposals/{$id}", [
            'type' => 'goal_breakdown_proposal',
            'goal_id' => $goal->id,
            'milestones' => [['title' => 'X']],
        ])->assertStatus(404);
    }

    public function test_accepted_proposal_can_no_longer_be_edited(): void
    {
        [, $token, $goal] = $this->userWithGoal();
        $id = $this->seedPendingProposal($goal->id, $token);
        $this->withToken($token)->postJson("/api/v1/ai/proposals/{$id}/accept")->assertStatus(200);

        $this->withToken($token)->putJson("/api/v1/ai/proposals/{$id}", [
            'type' => 'goal_breakdown_proposal',
            'goal_id' => $goal->id,
            'milestones' => [['title' => 'Late edit']],
        ])->assertStatus(422);
    }

    public function test_accept_applies_the_edited_payload(): void
    {
        [, $token, $goal] = $this->userWithGoal();
        $id = $this->seedPendingProposal($goal->id, $token);

        $this->withToken($token)->putJson("/api/v1/ai/proposals/{$id}", [
            'type' => 'goal_breakdown_proposal',
            'goal_id' => $goal->id,
            'milestones' => [
                ['title' => 'Discovery', 'target_date' => '2026-09-05', 'estimated_minutes' => 300],
                ['title' => 'Build MVP', 'estimated_minutes' => 1200],
            ],
        ])->assertStatus(200);

        $this->withToken($token)->postJson("/api/v1/ai/proposals/{$id}/accept")
            ->assertStatus(200);

        $titles = Milestone::query()->where('goal_id', $goal->id)->orderBy('sequence')->pluck('title')->all();
        $this->assertSame(['Discovery', 'Build MVP'], $titles);
        $this->assertSame(300, Milestone::query()->where('title', 'Discovery')->value('estimated_minutes'));
    }
}
