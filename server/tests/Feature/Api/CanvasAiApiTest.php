<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CanvasAiApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function fakeOllamaCanvas(array $sections = []): void
    {
        config([
            'ai.driver' => 'ollama',
            'ai.ollama.base_url' => 'http://localhost:11434',
            'ai.ollama.model' => 'llama3.1',
        ]);

        $sections = $sections ?: [
            ['name' => 'Goals', 'description' => 'Long-horizon outcomes'],
            ['name' => 'Risks', 'description' => 'What could go wrong'],
        ];

        Http::fake([
            'http://localhost:11434/api/generate' => Http::response([
                'response' => json_encode([
                    'type' => 'canvas_proposal',
                    'title' => 'Product Roadmap Canvas',
                    'sections' => $sections,
                ]),
            ], 200),
        ]);
    }

    public function test_suggest_canvas_requires_authentication(): void
    {
        $this->postJson('/api/v1/ai/suggest-canvas', ['prompt' => 'Design a roadmap canvas'])
            ->assertStatus(401);
    }

    public function test_suggest_canvas_generates_pending_proposal(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->fakeOllamaCanvas();

        $this->withToken($token)->postJson('/api/v1/ai/suggest-canvas', [
            'prompt' => 'Design a roadmap canvas',
        ])->assertStatus(200)
            ->assertJsonPath('proposal.proposal_type', 'canvas')
            ->assertJsonPath('proposal.decision', 'pending')
            ->assertJsonPath('proposal.payload.title', 'Product Roadmap Canvas')
            ->assertJsonCount(2, 'proposal.payload.sections');

        $this->assertDatabaseHas('ai_proposals', [
            'user_id' => $user->id,
            'proposal_type' => 'canvas',
            'decision' => 'pending',
        ]);

        // No canvas is created before acceptance (FR-62).
        $this->assertDatabaseCount('canvases', 0);
        $this->assertDatabaseHas('ai_runs', [
            'user_id' => $user->id,
            'proposal_type' => 'canvas',
            'status' => 'success',
        ]);
    }

    public function test_accept_creates_canvas(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->fakeOllamaCanvas();

        $response = $this->withToken($token)->postJson('/api/v1/ai/suggest-canvas', [
            'prompt' => 'Design a roadmap canvas',
        ])->assertStatus(200);
        $proposalId = $response->json('proposal.id');

        $this->withToken($token)->postJson("/api/v1/ai/proposals/{$proposalId}/accept")
            ->assertStatus(200)
            ->assertJsonPath('canvas.title', 'Product Roadmap Canvas');

        $this->assertDatabaseHas('canvases', [
            'user_id' => $user->id,
            'title' => 'Product Roadmap Canvas',
            'version' => 1,
        ]);
        $this->assertDatabaseHas('ai_proposals', [
            'id' => $proposalId,
            'decision' => 'accepted',
        ]);
    }

    public function test_reject_creates_no_canvas(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->fakeOllamaCanvas();

        $response = $this->withToken($token)->postJson('/api/v1/ai/suggest-canvas', [
            'prompt' => 'Design a roadmap canvas',
        ])->assertStatus(200);
        $proposalId = $response->json('proposal.id');

        $this->withToken($token)->postJson("/api/v1/ai/proposals/{$proposalId}/reject")
            ->assertStatus(200)
            ->assertJsonPath('proposal.decision', 'rejected');

        $this->assertDatabaseCount('canvases', 0);
    }

    public function test_suggest_canvas_rejects_invalid_output(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->fakeOllamaCanvas(['not-an-object']);

        $this->withToken($token)->postJson('/api/v1/ai/suggest-canvas', [
            'prompt' => 'Design a roadmap canvas',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'AI_OUTPUT_INVALID');

        $this->assertDatabaseCount('ai_proposals', 0);
    }

    public function test_suggest_canvas_validates_payload(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/ai/suggest-canvas', [
            'prompt' => '',
        ])->assertStatus(422);
    }

    public function test_accept_is_owner_scoped(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->fakeOllamaCanvas();

        $response = $this->withToken($token)->postJson('/api/v1/ai/suggest-canvas', [
            'prompt' => 'Design a roadmap canvas',
        ])->assertStatus(200);
        $proposalId = $response->json('proposal.id');

        $other = User::factory()->create();
        $otherToken = $other->createToken('owner')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->postJson("/api/v1/ai/proposals/{$proposalId}/accept")
            ->assertStatus(404);
        $this->assertDatabaseCount('canvases', 0);
    }
}
