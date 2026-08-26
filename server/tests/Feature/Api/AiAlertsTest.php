<?php

namespace Tests\Feature\Api;

use App\Models\AiCostAlert as AiCostAlertModel;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * TASK-P25-009/010 — AI Usage summary endpoint + cost alerts (domain events:
 * user usage thresholds, ops daily cost / anomaly; channels are out of scope).
 */
class AiAlertsTest extends TestCase
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

    private function openaiCosting(): array
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
            'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 0],
        ], 200)]);

        return [
            'ai.driver' => 'openai',
            'ai.openai.base_url' => 'https://api.openai.com/v1',
            'ai.openai.api_key' => 'sk-test',
            'ai.openai.model' => 'gpt-4o-mini',
            'ai.cost.catalog' => [
                'openai.gpt-4o-mini' => [
                    'currency' => 'USD',
                    'input_price_minor' => 1000,
                    'output_price_minor' => 1000,
                    'effective_from' => '2026-01-01',
                ],
            ],
        ];
    }

    public function test_usage_threshold_alert_fires_once_and_read_dismisses(): void
    {
        config([
            'ai.driver' => 'mock',
            'ai.alerts.usage_thresholds' => [50],
            'ai.alerts.ops_daily_cost_minor' => 0,
            'ai.alerts.user_anomaly_daily_requests' => 0,
            'saas.plans.free.entitlements.ai_credits' => 4,
        ]);
        [$user, $token] = $this->userWithToken();

        // Two of four credits → 50%: alert fires once and stays one row on refire.
        foreach (['one', 'two'] as $prompt) {
            $this->neutralizeAiRateLimit($user->id);
            $this->withToken($token)->postJson('/api/v1/ai/generate', [
                'role' => 'task_extraction', 'prompt' => $prompt,
            ])->assertStatus(200);
        }

        $this->withToken($token)->getJson('/api/v1/ai/alerts')
            ->assertOk()
            ->assertJsonCount(1, 'alerts')
            ->assertJsonPath('alerts.0.kind', 'user.usage_threshold')
            ->assertJsonPath('alerts.0.threshold', 50);

        $this->withToken($token)->postJson('/api/v1/ai/alerts/read')
            ->assertOk()
            ->assertJsonPath('marked_read', 1);

        $this->withToken($token)->getJson('/api/v1/ai/alerts')
            ->assertOk()
            ->assertJsonCount(0, 'alerts');
    }

    public function test_ops_daily_cost_alert_recorded_but_never_exposed_to_user(): void
    {
        config([
            ...$this->openaiCosting(),
            'ai.alerts.usage_thresholds' => [50],
            'ai.alerts.ops_daily_cost_minor' => 1000,
            'ai.alerts.user_anomaly_daily_requests' => 0,
        ]);
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction', 'prompt' => 'first',
        ])->assertStatus(200);

        // First run costs 1000 minor → equals cap; alert recorded (user_id NULL).
        $this->assertSame(1, AiCostAlertModel::query()->where('kind', 'ops.daily_cost')->count());
        $this->assertNull(AiCostAlertModel::query()->where('kind', 'ops.daily_cost')->first()->user_id);

        // Ops alerts are not in the user-facing list (only in-app user events).
        $this->withToken($token)->getJson('/api/v1/ai/alerts')
            ->assertOk()
            ->assertJsonCount(0, 'alerts');

        // Second run does not duplicate the once-per-day ops alert.
        $this->neutralizeAiRateLimit($user->id);
        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction', 'prompt' => 'second',
        ])->assertStatus(200);
        $this->assertSame(1, AiCostAlertModel::query()->where('kind', 'ops.daily_cost')->count());
    }

    public function test_ops_user_anomaly_alert_fires_and_is_not_user_visible(): void
    {
        config([
            'ai.driver' => 'mock',
            'ai.alerts.usage_thresholds' => [],
            'ai.alerts.ops_daily_cost_minor' => 0,
            'ai.alerts.user_anomaly_daily_requests' => 2,
        ]);
        [$user, $token] = $this->userWithToken();

        foreach (['one', 'two'] as $prompt) {
            $this->neutralizeAiRateLimit($user->id);
            $this->withToken($token)->postJson('/api/v1/ai/generate', [
                'role' => 'task_extraction', 'prompt' => $prompt,
            ])->assertStatus(200);
        }

        $anomaly = AiCostAlertModel::query()->where('kind', 'ops.user_anomaly')->first();
        $this->assertNotNull($anomaly);
        $this->assertSame($user->id, $anomaly->user_id);

        $this->withToken($token)->getJson('/api/v1/ai/alerts')
            ->assertOk()
            ->assertJsonCount(0, 'alerts');
    }

    public function test_usage_summary_reports_credits_ledger_cost_and_breakdown(): void
    {
        config($this->openaiCosting());
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction', 'prompt' => 'Anything',
        ])->assertStatus(200);

        $this->withToken($token)->getJson('/api/v1/ai/usage')
            ->assertOk()
            ->assertJsonPath('plan.code', 'free')
            ->assertJsonPath('credits.used', 1)
            ->assertJsonPath('credits.limit', 20)
            ->assertJsonPath('credits.remaining', 19)
            ->assertJsonPath('kinevo.request_count', 1)
            ->assertJsonPath('kinevo.estimated_cost_minor', 1000)
            ->assertJsonPath('kinevo.currency', 'USD')
            ->assertJsonPath('byok.request_count', 0)
            ->assertJsonPath('breakdown.0.type', 'text_generation')
            ->assertJsonPath('breakdown.0.count', 1)
            ->assertJsonPath('breakdown.0.kinevo_cost_minor', 1000)
            ->assertJsonPath('alerts.unread_count', 0);
    }

    public function test_usage_summary_separates_byok_runs_from_kinevo(): void
    {
        config([...$this->openaiCosting()]);
        [$user, $token] = $this->userWithToken();

        // BYOK is a Pro+ entitlement (locked business decision).
        $this->withToken($token)->patchJson('/api/v1/saas/plan', ['plan_code' => 'pro'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->putJson('/api/v1/ai/byok', [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'base_url' => 'https://api.example.com/v1',
            'api_key' => 'sk-secret-1234',
        ])->assertStatus(201);

        Http::fake(['api.example.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'byok']]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
        ], 200)]);

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'task_extraction', 'prompt' => 'BYOK call',
        ])->assertStatus(200);

        $this->withToken($token)->getJson('/api/v1/ai/usage')
            ->assertOk()
            ->assertJsonPath('kinevo.request_count', 0)
            ->assertJsonPath('byok.request_count', 1)
            ->assertJsonPath('credits.used', 0)
            ->assertJsonCount(0, 'breakdown');
    }
}
