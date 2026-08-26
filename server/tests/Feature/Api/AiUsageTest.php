<?php

namespace Tests\Feature\Api;

use App\Models\AiRun as AiRunModel;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * TASK-P25-001..005 — metered AI usage: per-run usage records (request
 * identity, credits, tokens), preflight denial before any provider call, and
 * postflight consumption that never burns a credit on failure.
 */
class AiUsageTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;
        $this->neutralizeAiRateLimit($user->id);

        return [$user, $token];
    }

    private function neutralizeAiRateLimit(int $userId): void
    {
        RateLimiter::for('ai', fn () => [Limit::none()]);
        RateLimiter::clear($userId.'|ai');
    }

    public function test_successful_generation_records_usage_and_spends_credit(): void
    {
        config(['ai.driver' => 'mock']);
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction',
            'prompt' => 'Extract tasks',
        ])->assertStatus(200);

        $run = AiRunModel::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($run);
        $this->assertSame('success', $run->status);
        $this->assertSame('text_generation', $run->proposal_type);
        $this->assertSame(1, $run->credits_consumed);
        $this->assertNotNull($run->request_id);
        $this->assertSame('mock', $run->provider);

        $this->withToken($token)->getJson('/api/v1/saas/plan')
            ->assertOk()
            ->assertJsonPath('usage.ai_credits.used', 1)
            ->assertJsonPath('usage.ai_credits.remaining', 19);

        // Run list exposes the new usage fields for settings/diagnostics.
        $this->withToken($token)->getJson('/api/v1/ai/runs')
            ->assertOk()
            ->assertJsonPath('runs.0.request_id', $run->request_id)
            ->assertJsonPath('runs.0.credits_consumed', 1)
            ->assertJsonPath('runs.0.proposal_type', 'text_generation');
    }

    public function test_exhausted_credits_denied_before_provider_without_new_run(): void
    {
        config(['ai.driver' => 'mock']);
        [$user, $token] = $this->userWithToken();

        for ($i = 0; $i < 20; $i++) {
            $this->neutralizeAiRateLimit($user->id);
            $this->withToken($token)->postJson('/api/v1/ai/generate', [
                'role' => 'natural_language_explanation', 'prompt' => "q{$i}",
            ])->assertStatus(200);
        }
        $this->app['auth']->forgetGuards();

        $this->neutralizeAiRateLimit($user->id);
        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'natural_language_explanation', 'prompt' => 'one too many',
        ])->assertStatus(403)
            ->assertJsonPath('code', 'ENTITLEMENT_LIMIT')
            ->assertJsonPath('entitlement', 'ai_credits')
            ->assertJsonPath('plan', 'free');

        $this->assertSame(20, AiRunModel::query()->where('user_id', $user->id)->count());
    }

    public function test_failed_generation_spends_nothing_and_records_failed_run(): void
    {
        config([
            'ai.driver' => 'openai',
            'ai.openai.base_url' => 'https://api.openai.com/v1',
            'ai.openai.api_key' => 'sk-test',
            'ai.openai.model' => 'gpt-4o-mini',
        ]);
        [$user, $token] = $this->userWithToken();

        Http::fake(['api.openai.com/*' => Http::response(['error' => 'boom'], 500)]);

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction',
            'prompt' => 'Anything',
        ])->assertStatus(503)
            ->assertJsonPath('code', 'AI_PROVIDER_UNAVAILABLE');

        $run = AiRunModel::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($run);
        $this->assertSame('failed', $run->status);
        $this->assertSame(0, $run->credits_consumed);
        $this->assertNotNull($run->request_id);

        $this->withToken($token)->getJson('/api/v1/saas/plan')
            ->assertOk()
            ->assertJsonPath('usage.ai_credits.used', 0)
            ->assertJsonPath('usage.ai_credits.remaining', 20);
    }

    public function test_successful_run_records_estimated_cost_from_price_catalog(): void
    {
        config([
            'ai.driver' => 'openai',
            'ai.openai.base_url' => 'https://api.openai.com/v1',
            'ai.openai.api_key' => 'sk-test',
            'ai.openai.model' => 'gpt-4o-mini',
            'ai.cost.catalog' => [
                'openai.gpt-4o-mini' => [
                    'currency' => 'USD',
                    'input_price_minor' => 5,
                    'output_price_minor' => 15,
                    'effective_from' => '2026-01-01',
                ],
            ],
        ]);
        [$user, $token] = $this->userWithToken();

        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
            'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 2000],
        ], 200)]);

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction',
            'prompt' => 'Anything',
        ])->assertStatus(200);

        $run = AiRunModel::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($run);
        $this->assertSame(35, (int) $run->estimated_cost_minor);
        $this->assertSame('USD', $run->cost_currency);
        $this->assertSame('catalog', $run->pricing_source);
        $this->assertNotNull($run->pricing_snapshot_id);
    }

    public function test_proposal_paths_also_spend_credits(): void
    {
        config([
            'ai.driver' => 'ollama',
            'ai.ollama.base_url' => 'http://localhost:11434',
            'ai.ollama.model' => 'llama3.1',
        ]);
        [$user, $token] = $this->userWithToken();

        Http::fake(fn () => Http::response([
            'response' => json_encode([
                'type' => 'summary_proposal',
                'summary' => 'A concise summary.',
                'key_points' => ['point one', 'point two'],
            ]),
        ], 200));

        $this->withToken($token)->postJson('/api/v1/ai/proposals', [
            'type' => 'summary',
            'prompt' => 'Summarize my notes',
        ])->assertStatus(200);

        $run = AiRunModel::query()->where('user_id', $user->id)->first();
        $this->assertSame('summary', $run->proposal_type);
        $this->assertSame(1, $run->credits_consumed);
        $this->assertNotNull($run->request_id);
    }
}
