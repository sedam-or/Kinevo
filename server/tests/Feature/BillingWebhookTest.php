<?php

namespace Tests\Feature;

use App\Models\BillingSubscription;
use App\Models\BillingTransaction;
use App\Models\SaasSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** TASK-P24-013..016/024 — webhook verification, idempotency, transitions, entitlement sync. */
class BillingWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function key(): string
    {
        return 'test-server-key';
    }

    protected function seedBilling(): array
    {
        config(['billing.midtrans.server_key' => $this->key(), 'billing.midtrans.webhook_verify' => true, 'billing.midtrans.base_url' => 'https://api.sandbox.midtrans.com']);
        $user = User::factory()->create();
        $sub = BillingSubscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'pro',
            'price_amount_minor' => 4_900_000,
            'provider' => 'midtrans',
            'operation_id' => 'kinevo-op-1',
            'provider_subscription_id' => 'sub-77',
            'state' => 'pending',
        ]);
        // P23 resolver row starts on free fallback.
        SaasSubscription::query()->create([
            'user_id' => $user->id, 'plan_code' => 'free',
            'provider' => 'manual', 'state' => 'active',
        ]);

        return [$user, $sub];
    }

    private function payload(array $overrides = []): string
    {
        $body = array_merge([
            'order_id' => 'order-9', 'status_code' => '200', 'gross_amount' => '49000.00',
            'transaction_status' => 'settlement', 'transaction_id' => 'tx-1',
            'transaction_time' => now()->toIso8601String(),
            'subscription_id' => 'sub-77',
            'metadata' => ['kinevo_user_id' => null, 'kinevo_plan_code' => 'pro'],
            'currency' => 'IDR',
        ], $overrides);
        $body['signature_key'] = hash('sha512', $body['order_id'].$body['status_code'].$body['gross_amount'].$this->key());

        return json_encode($body);
    }

    public function test_settlement_activates_subscription_and_syncs_entitlement(): void
    {
        [$user, $sub] = $this->seedBilling();

        $this->postJson('/api/v1/billing/webhook/midtrans', [], [])
            ->assertStatus(403); // empty payload malformed

        $res = $this->call('POST', '/api/v1/billing/webhook/midtrans', [], [], [],
            ['CONTENT_TYPE' => 'application/json'], $this->payload(['metadata' => ['kinevo_user_id' => $user->id, 'kinevo_plan_code' => 'pro']]))
            ->assertOk();

        $res->assertJsonPath('status', 'applied');
        $this->assertDatabaseHas('billing_subscriptions', ['id' => $sub->id, 'state' => 'active']);
        $this->assertDatabaseHas('billing_transactions', [
            'provider_transaction_id' => 'tx-1', 'status' => 'succeeded', 'amount_minor' => 4_900_000, // 49000.00 IDR x100
        ]);
        $this->assertDatabaseHas('subscriptions', ['id' => SaasSubscription::query()->where('user_id', $user->id)->first()->id, 'plan_code' => 'pro', 'state' => 'active']);
    }

    public function test_duplicate_event_is_safe_noop(): void
    {
        [$user, $sub] = $this->seedBilling();
        $raw = $this->payload(['metadata' => ['kinevo_user_id' => $user->id, 'kinevo_plan_code' => 'pro']]);

        $this->call('POST', '/api/v1/billing/webhook/midtrans', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw)->assertOk();
        $firstCount = BillingTransaction::count();
        $this->call('POST', '/api/v1/billing/webhook/midtrans', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw)
            ->assertOk()->assertJsonPath('status', 'duplicate');
        $this->assertSame($firstCount, BillingTransaction::count());
    }

    public function test_invalid_signature_rejected_and_audited_not_applied(): void
    {
        [$user, $sub] = $this->seedBilling();
        $bad = str_replace('"signature_key":"', '"signature_key":"0', $this->payload());
        $this->call('POST', '/api/v1/billing/webhook/midtrans', [], [], [], ['CONTENT_TYPE' => 'application/json'], $bad)
            ->assertStatus(403);
        $this->assertDatabaseHas('billing_subscriptions', ['id' => $sub->id, 'state' => 'pending']);
    }

    public function test_out_of_order_event_cannot_regress_state(): void
    {
        [$user, $sub] = $this->seedBilling();
        // First: settle now (activates).
        $this->call('POST', '/api/v1/billing/webhook/midtrans', [], [], [], ['CONTENT_TYPE' => 'application/json'], $this->payload(['metadata' => ['kinevo_user_id' => $user->id]]))->assertOk();
        // Then: an OLDER pending event arrives — must not regress to past_due.
        $old = $this->payload([
            'transaction_status' => 'pending',
            'transaction_time' => now()->subDays(2)->toIso8601String(),
            'transaction_id' => 'tx-0',
            'signature_key' => hash('sha512', 'order-9200 49000.00'.$this->key()),
        ]);
        $old = str_replace('"order_id":"order-9"', '"order_id":"order-9"', $old);
        // rebuild signature properly for modified fields:
        $arr = json_decode($old, true);
        unset($arr['signature_key']);
        $sig = hash('sha512', $arr['order_id'].$arr['status_code'].$arr['gross_amount'].$this->key());
        $arr['signature_key'] = $sig;
        $this->call('POST', '/api/v1/billing/webhook/midtrans', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($arr))
            ->assertOk()
            ->assertJsonPath('status', 'out_of_order');

        $this->assertDatabaseHas('billing_subscriptions', ['id' => $sub->id, 'state' => 'active']);
    }
}
