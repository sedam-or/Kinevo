<?php

namespace Tests\Unit\Billing;

use App\Domain\Billing\BillingEventType;
use App\Domain\Billing\SubscriptionState;
use App\Infrastructure\Billing\MidtransGateway;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** TASK-P24-004/033 — capability honesty + deterministic mapping + webhook verification. */
class MidtransGatewayTest extends TestCase
{
    private function gateway(): MidtransGateway
    {
        return new MidtransGateway('SB-Mid-server-testkey');
    }

    #[Test]
    public function capabilities_never_claim_unverified_support(): void
    {
        $caps = $this->gateway()->capabilities()->toArray();
        $this->assertSame('SUPPORTED', $caps['provider_managed_recurring']);
        $this->assertSame('SUPPORTED', $caps['refund']);    // verified 2026-08-27 (refund-transaction doc)
        $this->assertSame('SUPPORTED', $caps['dispute']);   // chargeback notifications (dashboard simulator)
        $this->assertContains('Recurring activation by Midtrans Support/Sales', $caps['merchant_prerequisites']);
        $this->assertContains('Chargeback resolution is handled via Midtrans Dashboard (manual); webhook carries opened/partial status only', $caps['limitations']);
    }

    #[Test]
    public function provider_statuses_map_deterministically(): void
    {
        $g = $this->gateway();
        $this->assertSame(SubscriptionState::Active, $g->mapStatus('ACTIVE'));
        $this->assertSame(SubscriptionState::Pending, $g->mapStatus('created'));
        $this->assertSame(SubscriptionState::Canceled, $g->mapStatus('inactive'));
        $this->assertSame(SubscriptionState::Expired, $g->mapStatus('expire'));
    }

    #[Test]
    public function unmapped_status_throws_instead_of_guessing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->gateway()->mapStatus('warp-drive');
    }

    private function payload(string $status = 'settlement', ?string $sig = null): string
    {
        $body = [
            'order_id' => 'order-1', 'status_code' => '200', 'gross_amount' => '100000.00',
            'transaction_status' => $status, 'transaction_id' => 'tx-9',
            'transaction_time' => '2026-08-26T10:00:00+07:00',
        ];
        $body['signature_key'] = $sig ?? hash('sha512', $body['order_id'].$body['status_code'].$body['gross_amount'].'SB-Mid-server-testkey');

        return json_encode($body);
    }

    #[Test]
    public function valid_signature_normalizes_to_payment_succeeded(): void
    {
        $event = $this->gateway()->verifyAndNormalizeWebhook($this->payload(), []);
        $this->assertSame(BillingEventType::PaymentSucceeded, $event->type);
        $this->assertSame(SubscriptionState::Active, $event->newState);
        $this->assertSame('tx-9', $event->providerTransactionId);
    }

    #[Test]
    public function invalid_signature_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->gateway()->verifyAndNormalizeWebhook($this->payload(sig: strrev(hash('sha512', 'x'))), []);
    }

    #[Test]
    public function refund_status_normalizes_to_refund_created(): void
    {
        $event = $this->gateway()->verifyAndNormalizeWebhook($this->payload(status: 'refund'), []);
        $this->assertSame(BillingEventType::RefundCreated, $event->type);
        $this->assertNull($event->newState); // refund never touches subscription state
    }

    #[Test]
    public function partial_refund_and_chargeback_statuses_normalize(): void
    {
        $g = $this->gateway();
        $this->assertSame(BillingEventType::RefundCreated, $g->verifyAndNormalizeWebhook($this->payload(status: 'partial_refund'), [])->type);
        $this->assertSame(BillingEventType::ChargebackOpened, $g->verifyAndNormalizeWebhook($this->payload(status: 'chargeback'), [])->type);
        $this->assertSame(BillingEventType::ChargebackOpened, $g->verifyAndNormalizeWebhook($this->payload(status: 'partial_chargeback'), [])->type);
    }

    #[Test]
    public function malformed_payload_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->gateway()->verifyAndNormalizeWebhook(json_encode(['order_id' => 'x']), []);
    }
}
