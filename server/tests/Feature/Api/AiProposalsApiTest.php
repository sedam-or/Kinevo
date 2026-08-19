<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProposalsApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function fakeOllama(array $byPrompt): void
    {
        config([
            'ai.driver' => 'ollama',
            'ai.ollama.base_url' => 'http://localhost:11434',
            'ai.ollama.model' => 'llama3.1',
        ]);

        Http::fake(function ($request) use ($byPrompt) {
            $prompt = (string) data_get($request->data(), 'prompt', '');

            foreach ($byPrompt as $needle => $body) {
                if (str_contains($prompt, $needle)) {
                    return Http::response(['response' => $body], 200);
                }
            }

            return Http::response(['response' => '{}'], 200);
        });
    }

    public function test_proposals_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/v1/ai/proposals', [
            'type' => 'summary',
            'prompt' => 'Summarize',
        ])->assertStatus(401);

        $this->getJson('/api/v1/ai/runs')->assertStatus(401);
    }

    public function test_valid_proposal_is_returned_and_audited(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->fakeOllama([
            'Summarize' => json_encode([
                'type' => 'summary_proposal',
                'summary' => 'A concise summary.',
                'key_points' => ['point one', 'point two'],
            ]),
        ]);

        $this->withToken($token)->postJson('/api/v1/ai/proposals', [
            'type' => 'summary',
            'prompt' => 'Summarize my notes',
        ])->assertStatus(200)
            ->assertJsonPath('proposal.proposal_type', 'summary')
            ->assertJsonPath('proposal.schema_version', 1)
            ->assertJsonPath('proposal.decision', 'pending')
            ->assertJsonPath('proposal.payload.summary', 'A concise summary.');

        $this->assertDatabaseHas('ai_runs', [
            'user_id' => $user->id,
            'proposal_type' => 'summary',
            'status' => 'success',
            'provider' => 'ollama',
        ]);
    }

    public function test_malformed_json_returns_422_and_is_never_persisted(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->fakeOllama(['broken' => '{"summary": "broken']);

        $this->withToken($token)->postJson('/api/v1/ai/proposals', [
            'type' => 'summary',
            'prompt' => 'Summarize my notes',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'AI_OUTPUT_INVALID');

        $this->assertDatabaseCount('ai_proposals', 0);
        $this->assertDatabaseHas('ai_runs', [
            'user_id' => $user->id,
            'status' => 'failed',
            'error_code' => 'AI_OUTPUT_INVALID',
        ]);
    }

    public function test_schema_violation_returns_422(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->fakeOllama([
            'Summarize' => json_encode([
                'type' => 'summary_proposal',
                'summary' => 'S',
                'key_points' => [],
            ]),
        ]);

        $this->withToken($token)->postJson('/api/v1/ai/proposals', [
            'type' => 'summary',
            'prompt' => 'Summarize my notes',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'AI_OUTPUT_INVALID');
    }

    public function test_disabled_provider_returns_503_and_audits_failure(): void
    {
        [$user, $token] = $this->userWithToken();
        config(['ai.driver' => 'disabled']);

        $this->withToken($token)->postJson('/api/v1/ai/proposals', [
            'type' => 'summary',
            'prompt' => 'Summarize my notes',
        ])->assertStatus(503)
            ->assertJsonPath('code', 'AI_PROVIDER_UNAVAILABLE');

        $this->assertDatabaseHas('ai_runs', [
            'user_id' => $user->id,
            'status' => 'failed',
            'error_code' => 'AI_PROVIDER_UNAVAILABLE',
        ]);
    }

    public function test_proposals_validates_payload(): void
    {
        [$user, $token] = $this->userWithToken();
        config(['ai.driver' => 'mock']);

        $this->withToken($token)->postJson('/api/v1/ai/proposals', [
            'type' => 'not_a_type',
            'prompt' => 'x',
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/ai/proposals', [
            'type' => 'summary',
            'prompt' => '',
        ])->assertStatus(422);
    }

    public function test_runs_are_scoped_and_filterable(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();

        $this->fakeOllama([
            'Summarize' => json_encode([
                'type' => 'summary_proposal',
                'summary' => 'S',
                'key_points' => ['a'],
            ]),
            'Extract' => json_encode([
                'type' => 'task_extraction_proposal',
                'tasks' => [['title' => 'Write docs']],
            ]),
        ]);

        $this->withToken($token)->postJson('/api/v1/ai/proposals', [
            'type' => 'summary',
            'prompt' => 'Summarize',
        ])->assertStatus(200);

        $this->withToken($token)->postJson('/api/v1/ai/proposals', [
            'type' => 'task_extraction',
            'prompt' => 'Extract',
        ])->assertStatus(200);

        $this->withToken($token)->getJson('/api/v1/ai/runs')
            ->assertStatus(200)
            ->assertJsonCount(2, 'runs');

        $this->withToken($token)->getJson('/api/v1/ai/runs?proposal_type=summary')
            ->assertStatus(200)
            ->assertJsonCount(1, 'runs')
            ->assertJsonPath('runs.0.proposal_type', 'summary');

        $otherToken = $other->createToken('owner')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/ai/runs')
            ->assertStatus(200)
            ->assertJsonCount(0, 'runs');
    }
}
