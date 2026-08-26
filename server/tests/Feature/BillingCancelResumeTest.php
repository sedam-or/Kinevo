<?php

namespace Tests\Feature;

use App\Models\BillingSubscription;
use App\Models\SaasSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** TASK-P24-020 — cancel/resume lifecycle. */
class BillingCancelResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_disables_provider_and_downgrades_to_free(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        BillingSubscription::query()->create([
            'user_id' => $user->id, 'plan_code' => 'personal',
            'price_amount_minor' => 4_900_000, 'provider' => 'midtrans',
            'operation_id' => 'op-1', 'provider_subscription_id' => 'sub-cx',
            'state' => 'active',
        ]);
        SaasSubscription::query()->create([
            'user_id' => $user->id, 'plan_code' => 'personal',
            'provider' => 'midtrans', 'state' => 'active',
        ]);
        config(['billing.midtrans.server_key' => 'test-key']);
        Http::fake(['*/enable' => Http::response([], 200), '*/disable' => Http::response([], 200)]);

        $this->withToken($token)->postJson('/api/v1/billing/cancel')
            ->assertOk()
            ->assertJsonPath('status', 'canceled');

        $this->assertDatabaseHas('billing_subscriptions', ['user_id' => $user->id, 'state' => 'canceled']);
        // Downgraded to free.
        $saas = SaasSubscription::query()->where('user_id', $user->id)->first();
        $this->assertSame('free', $saas->plan_code);
    }

    public function test_resume_restores_paid_entitlement(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        BillingSubscription::query()->create([
            'user_id' => $user->id, 'plan_code' => 'personal',
            'price_amount_minor' => 4_900_000, 'provider' => 'midtrans',
            'operation_id' => 'op-2', 'provider_subscription_id' => 'sub-rs',
            'state' => 'canceled',
        ]);
        SaasSubscription::query()->create([
            'user_id' => $user->id, 'plan_code' => 'free',
            'provider' => 'manual', 'state' => 'active',
        ]);
        config(['billing.midtrans.server_key' => 'test-key']);
        Http::fake(['*/enable' => Http::response([], 200), '*/disable' => Http::response([], 200)]);

        $this->withToken($token)->postJson('/api/v1/billing/resume')
            ->assertOk()
            ->assertJsonPath('status', 'resumed');

        $this->assertDatabaseHas('billing_subscriptions', ['user_id' => $user->id, 'state' => 'active']);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id, 'plan_code' => 'personal']);
    }

    public function test_cancel_without_subscription_returns_404(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/billing/cancel')->assertStatus(404);
    }
}
