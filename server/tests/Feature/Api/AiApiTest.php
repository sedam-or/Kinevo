<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    public function test_ai_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/ai/status')->assertStatus(401);
        $this->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction',
            'prompt' => 'Anything',
        ])->assertStatus(401);
    }

    public function test_mock_provider_generates_text(): void
    {
        config(['ai.driver' => 'mock']);
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction',
            'prompt' => 'Extract tasks from this text',
        ])->assertStatus(200)
            ->assertJsonPath('provider', 'mock')
            ->assertJsonPath('model', 'mock-1')
            ->assertJsonPath('text', 'Mock AI response [task_extraction]: Extract tasks from this text');
    }

    public function test_ollama_generate_encodes_empty_options_as_object(): void
    {
        // Regression (TASK-P17-032 real-provider verification): json_encode([])
        // emits a JSON array, but Ollama requires options to be a map and
        // rejects the array with HTTP 400 — breaking every generation that
        // carries no explicit temperature/max_tokens.
        config([
            'ai.driver' => 'ollama',
            'ai.ollama.base_url' => 'http://localhost:11434',
            'ai.ollama.model' => 'llama3.1',
        ]);
        [$user, $token] = $this->userWithToken();

        $captured = null;
        Http::fake(function ($request) use (&$captured) {
            $captured = $request->body();

            return Http::response(['response' => 'ok'], 200);
        });

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'natural_language_explanation',
            'prompt' => 'Explain this.',
        ])->assertStatus(200);

        $this->assertNotNull($captured);
        $this->assertStringContainsString('"options":{}', $captured);
        $this->assertStringNotContainsString('"options":[]', $captured);
    }

    public function test_mock_provider_reports_status(): void
    {
        config(['ai.driver' => 'mock']);
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->getJson('/api/v1/ai/status')
            ->assertStatus(200)
            ->assertJsonPath('status.provider', 'mock')
            ->assertJsonPath('status.available', true)
            ->assertJsonPath('status.state', 'connected');
    }

    public function test_disabled_provider_returns_503_with_canonical_code(): void
    {
        config(['ai.driver' => 'disabled']);
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction',
            'prompt' => 'Anything',
        ])->assertStatus(503)
            ->assertJsonPath('code', 'AI_PROVIDER_UNAVAILABLE');

        $this->withToken($token)->getJson('/api/v1/ai/status')
            ->assertStatus(200)
            ->assertJsonPath('status.available', false)
            ->assertJsonPath('status.state', 'disabled');
    }

    public function test_generate_validates_role_and_prompt(): void
    {
        config(['ai.driver' => 'mock']);
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'not_a_role',
            'prompt' => 'Anything',
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction',
            'prompt' => '',
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction',
            'prompt' => 'x',
            'temperature' => 3,
        ])->assertStatus(422);
    }

    public function test_generate_honors_system_prompt_and_options(): void
    {
        config(['ai.driver' => 'mock']);
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'goal_decomposition',
            'prompt' => 'Break this goal down',
            'system_prompt' => 'You are concise.',
            'temperature' => 0.5,
            'max_tokens' => 128,
        ])->assertStatus(200)
            ->assertJsonPath('text', 'Mock AI response [goal_decomposition]: Break this goal down');
    }
}
