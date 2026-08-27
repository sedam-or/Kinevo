<?php

namespace Tests\Feature;

use App\Infrastructure\Billing\MidtransGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** TASK-P24-022 — merchant-initiated refund via Midtrans Core API. */
class BillingRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_transaction_posts_core_refund_api_with_key_and_amount(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v2/order-9/refund' => Http::response([
                'status_code' => 200, 'status_message' => 'success',
                'transaction_id' => 'tx-1', 'refund_key' => 'refund-1', 'amount' => 49000,
            ], 200),
        ]);

        $gateway = new MidtransGateway('test-server-key', 'https://api.sandbox.midtrans.com');
        $result = $gateway->refundTransaction('order-9', amountMinor: 4_900_000, refundKey: 'refund-1');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.sandbox.midtrans.com/v2/order-9/refund'
            && $request['amount'] === 49000
            && $request['refund_key'] === 'refund-1');
        $this->assertSame('success', $result['status_message']);
    }

    public function test_refund_api_error_is_surfaced(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v2/order-9/refund' => Http::response([], 405),
        ]);

        $gateway = new MidtransGateway('test-server-key', 'https://api.sandbox.midtrans.com');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MIDTRANS_REFUND_ERROR_405');
        $gateway->refundTransaction('order-9');
    }
}
