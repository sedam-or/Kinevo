<?php

namespace Tests\Feature;

use App\Models\BillingSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** TASK-P24-010/011 — backend checkout via Midtrans Subscription API + idempotency. */
class BillingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_pending_subscription_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        config(['billing.midtrans.server_key' => 'test-key', 'billing.midtrans.base_url' => 'https://api.sandbox.midtrans.com']);

        Http::fake([
            'api.sandbox.midtrans.com/v1/subscriptions' => Http::response([
                'id' => 'sub-prov-1', 'status' => 'CREATED',
                'payment_type' => 'card',
            ], 201),
        ]);

        $first = $this->withToken($token)->postJson('/api/v1/billing/checkout', ['plan_code' => 'personal'])
            ->assertStatus(201)
            ->assertJsonPath('provider_subscription_id', 'sub-prov-1')
            ->assertJsonPath('status', 'pending');

        // Second identical request reuses the pending row — no duplicate provider sub.
        $second = $this->withToken($token)->postJson('/api/v1/billing/checkout', ['plan_code' => 'personal'])
            ->assertStatus(201)
            ->assertJsonPath('billing_subscription_id', $first->json('billing_subscription_id'));

        Http::assertSentCount(1);
        $this->assertSame(1, BillingSubscription::count());
    }

    public function test_gateway_unavailable_fails_clearly_without_local_state(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        config(['billing.midtrans.server_key' => 'test-key']);

        Http::fake(fn () => throw new ConnectionException('down'));

        $this->withToken($token)->postJson('/api/v1/billing/checkout', ['plan_code' => 'pro'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'GATEWAY_ERROR');

        $this->assertSame(0, BillingSubscription::count());
    }

    public function test_free_plan_is_rejected_for_checkout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/billing/checkout', ['plan_code' => 'free'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'The Free plan does not require checkout.');
    }
}
