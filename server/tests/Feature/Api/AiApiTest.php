<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
