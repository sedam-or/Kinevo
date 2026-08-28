<?php

namespace Tests\Feature\Api;

use App\Models\SaasSubscription;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * TASK-P23-009 — SaaS domain behaviour: plan switching, entitlement gating
 * (workspaces / AI credits / export), usage accounting and expired-state
 * degradation. Backend enforcement only — the UI merely explains.
 */
class SaasApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;
        // Provision Personal default workspace without polluting counters.
        $this->withToken($token)->getJson('/api/v1/workspaces');

        return [$user, $token];
    }

    public function test_plan_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/saas/plan')->assertStatus(401);
        $this->patchJson('/api/v1/saas/plan', ['plan_code' => 'pro'])->assertStatus(401);
    }

    public function test_default_plan_is_free_with_usage_snapshot(): void
    {
        [, $token] = $this->userWithToken();

        $this->withToken($token)->getJson('/api/v1/saas/plan')
            ->assertOk()
            ->assertJsonPath('plan.code', 'free')
            ->assertJsonPath('subscription.state', 'active')
            ->assertJsonPath('usage.ai_credits.allowance', 20)
            ->assertJsonPath('usage.ai_credits.used', 0)
            ->assertJsonPath('usage.ai_credits.remaining', 20)
            // COMMERCIAL PRICING DELTA — locked launch prices in WHOLE Rupiah
            // (49_900 / 89_900); amount_minor is the derived cent-equivalent.
            ->assertJsonPath('pricing.free.amount_major', 0)
            ->assertJsonPath('pricing.pro.amount_major', 49_900)
            ->assertJsonPath('pricing.pro.amount_minor', 4_990_000)
            ->assertJsonPath('pricing.power.amount_major', 89_900)
            ->assertJsonPath('pricing.power.amount_minor', 8_990_000)
            ->assertJsonPath('pricing.pro.launch_hypothesis', true);
    }

    public function test_switching_plan_updates_entitlements(): void
    {
        [, $token] = $this->userWithToken();

        $this->withToken($token)->patchJson('/api/v1/saas/plan', ['plan_code' => 'power'])
            ->assertOk()
            ->assertJsonPath('plan.code', 'power')
            ->assertJsonPath('usage.ai_credits.allowance', 1000);

        $this->withToken($token)->patchJson('/api/v1/saas/plan', ['plan_code' => 'nope'])
            ->assertStatus(422);
    }

    public function test_max_workspaces_is_enforced_on_free_and_upgraded_on_paid(): void
    {
        [, $token] = $this->userWithToken(); // free: max 1 (Personal default only)

        $denied = $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => 'Second']);
        $denied->assertStatus(403)
            ->assertJsonPath('code', 'ENTITLEMENT_LIMIT')
            ->assertJsonPath('entitlement', 'max_workspaces')
            ->assertJsonPath('limit', 1);

        // Upgrade unlocks it (COMMERCIAL PRICING DELTA matrix: Pro = 5).
        $this->withToken($token)->patchJson('/api/v1/saas/plan', ['plan_code' => 'pro']);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => 'Second'])
            ->assertStatus(201);
        $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => 'Third'])
            ->assertStatus(201)
            ->assertJsonPath('workspace.name', 'Third');
    }

    public function test_ai_credits_are_consumed_then_exhausted(): void
    {
        [$user, $token] = $this->userWithToken();
        config(['ai.driver' => 'mock']);
        // Credits and request-throttling are separate controls; neutralize
        // the per-minute limiter here so THIS test exercises only credits.
        RateLimiter::for('ai', fn () => [Limit::none()]);
        RateLimiterHelper::clearAi($user->id);

        for ($i = 0; $i < 20; $i++) {
            $this->withToken($token)->postJson('/api/v1/ai/generate', [
                'role' => 'natural_language_explanation', 'prompt' => "q{$i}",
            ])->assertStatus(200);
        }
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'natural_language_explanation', 'prompt' => 'one too many',
        ])->assertStatus(403)
            ->assertJsonPath('code', 'ENTITLEMENT_LIMIT')
            ->assertJsonPath('entitlement', 'ai_credits');

        // Usage snapshot reflects exhaustion; upgrade restores allowance.
        $this->withToken($token)->getJson('/api/v1/saas/plan')
            ->assertOk()
            ->assertJsonPath('usage.ai_credits.used', 20)
            ->assertJsonPath('usage.ai_credits.remaining', 0);
    }

    public function test_export_requires_entitlement(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/export', ['format' => 'json'])
            ->assertStatus(403)
            ->assertJsonPath('entitlement', 'export');

        $this->withToken($token)->patchJson('/api/v1/saas/plan', ['plan_code' => 'pro']);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/export', ['format' => 'json'])->assertOk();
    }

    public function test_expired_subscription_degrades_to_free(): void
    {
        [$user, $token] = $this->userWithToken();
        SaasSubscription::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['plan_code' => 'pro', 'provider' => 'manual', 'state' => 'expired'],
        );

        $this->withToken($token)->getJson('/api/v1/saas/plan')
            ->assertOk()
            ->assertJsonPath('plan.code', 'free') // degraded
            ->assertJsonPath('subscription.state', 'expired');
    }

    public function test_downgrade_never_deletes_existing_data_but_blocks_new_usage(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->patchJson('/api/v1/saas/plan', ['plan_code' => 'power']);
        $this->app['auth']->forgetGuards();

        // Build workspace state while on Power (max 15).
        $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => 'A'])->assertStatus(201);
        $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => 'B'])->assertStatus(201);

        // Downgrade Power -> Pro -> Free (COMMERCIAL PRICING DELTA §16).
        $this->withToken($token)->patchJson('/api/v1/saas/plan', ['plan_code' => 'free']);
        $this->app['auth']->forgetGuards();

        // Existing workspaces survive the downgrade (never silently deleted).
        $this->withToken($token)->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertJsonCount(3, 'workspaces') // Personal default + A + B
            ->assertJsonFragment(['name' => 'A'])
            ->assertJsonFragment(['name' => 'B'])
            ->assertJsonStructure(['default_workspace_id']);

        // New creation beyond the Free limit (1) is blocked, not data-destroying.
        $this->withToken($token)->postJson('/api/v1/workspaces', ['name' => 'C'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'ENTITLEMENT_LIMIT')
            ->assertJsonPath('entitlement', 'max_workspaces')
            ->assertJsonPath('limit', 1);
    }
}

/** Small helper to reset the AI rate limiter between credit-loop iterations. */
class RateLimiterHelper
{
    public static function clearAi(int $userId): void
    {
        RateLimiter::clear($userId.'|ai');
    }
}
