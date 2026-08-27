<?php

namespace Tests\Feature;

use App\Models\BillingSubscription;
use App\Models\BillingTransaction;
use App\Models\SaasSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-P26-006 — mobile read contract for subscription state. Android v1 reads
 * `/billing/subscription` (plus `/saas/plan`) to show the current tier; this
 * locks the wire shape so the client never sees raw provider payloads.
 */
class BillingSubscriptionReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_subscription_returns_null_safely(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/billing/subscription')
            ->assertOk()
            ->assertJsonPath('subscription', null)
            ->assertJsonPath('transactions', []);
    }

    public function test_subscription_snapshot_exposes_mobile_safe_shape(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $sub = BillingSubscription::query()->create([
            'user_id' => $user->id, 'plan_code' => 'pro',
            'price_amount_minor' => 4_990_000, 'price_currency' => 'IDR',
            'provider' => 'midtrans', 'operation_id' => 'kinevo-op-read',
            'provider_subscription_id' => 'sub-r1', 'state' => 'active',
        ]);
        BillingTransaction::query()->create([
            'user_id' => $user->id, 'billing_subscription_id' => $sub->id,
            'provider' => 'midtrans', 'provider_transaction_id' => 'tx-r1',
            'amount_minor' => 4_990_000, 'currency' => 'IDR',
            'status' => 'succeeded', 'occurred_at' => now(),
        ]);
        SaasSubscription::query()->create([
            'user_id' => $user->id, 'plan_code' => 'pro',
            'provider' => 'manual', 'state' => 'active',
        ]);

        $res = $this->withToken($token)->getJson('/api/v1/billing/subscription')
            ->assertOk()
            ->assertJsonPath('subscription.plan_code', 'pro')
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.price_amount_minor', 4_990_000)
            ->assertJsonPath('subscription.currency', 'IDR');

        $this->assertSame(['plan_code', 'status', 'price_amount_minor', 'currency', 'uncertain'], array_keys($res->json('subscription')));
        $this->assertSame('succeeded', $res->json('transactions.0.status'));
        $this->assertSame(4_990_000, $res->json('transactions.0.amount_minor'));
        // Mobile-safe shape: no provider id, no provider name, no raw payload.
        $this->assertArrayNotHasKey('provider_transaction_id', $res->json('transactions.0'));
        $this->assertArrayNotHasKey('provider', $res->json('transactions.0'));
    }

    public function test_transaction_list_is_capped_to_twenty(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $sub = BillingSubscription::query()->create([
            'user_id' => $user->id, 'plan_code' => 'power',
            'price_amount_minor' => 8_990_000, 'provider' => 'midtrans',
            'operation_id' => 'kinevo-op-cap', 'provider_subscription_id' => 'sub-r2', 'state' => 'active',
        ]);
        for ($i = 1; $i <= 25; $i++) {
            BillingTransaction::query()->create([
                'user_id' => $user->id, 'billing_subscription_id' => $sub->id,
                'provider' => 'midtrans', 'provider_transaction_id' => 'tx-cap-'.$i,
                'amount_minor' => 8_990_000, 'currency' => 'IDR',
                'status' => 'succeeded', 'occurred_at' => now()->subDays($i),
            ]);
        }

        $res = $this->withToken($token)->getJson('/api/v1/billing/subscription')->assertOk();
        $this->assertCount(20, $res->json('transactions'));
    }
}
