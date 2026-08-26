<?php

namespace App\Infrastructure\Billing;

use App\Domain\Billing\BillingEventType;
use App\Domain\Billing\GatewayCapabilities;
use App\Domain\Billing\NormalizedBillingEvent;
use App\Domain\Billing\PaymentGateway;
use App\Domain\Billing\SubscriptionState;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * TASK-P24-004 — Midtrans adapter (verified against docs.midtrans.com
 * Subscription API / API Methods / GoPay Tokenization, 2026-08-26).
 * HTTP transport is intentionally NOT wired here yet: P24-012+ wires the
 * subscription lifecycle once sandbox credentials exist (BLOCKED note in TASK.md).
 */
final readonly class MidtransGateway implements PaymentGateway
{
    public function __construct(
        private string $serverKey,
        private string $baseUrl = 'https://api.sandbox.midtrans.com',
    ) {}

    /** TASK-P24-010 — create a provider-managed subscription (recurring). */
    public function createSubscription(array $payload): array
    {
        try {
            $response = Http::timeout(30)
                ->withBasicAuth($this->serverKey, '')
                ->acceptJson()
                ->post($this->baseUrl.'/v1/subscriptions', $payload);
        } catch (ConnectionException) {
            throw new RuntimeException('Midtrans is unreachable.');
        }

        if ($response->status() === 429) {
            throw new RuntimeException('MIDTRANS_RATE_LIMITED');
        }
        if (! $response->successful()) {
            throw new RuntimeException('MIDTRANS_ERROR_'.$response->status().': '.substr((string) $response->body(), 0, 200));
        }

        return (array) $response->json();
    }

    /** TASK-P24-012 — lifecycle: get / enable / disable. */
    public function getSubscription(string $providerSubscriptionId): array
    {
        return (array) Http::timeout(20)
            ->withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->get($this->baseUrl.'/v1/subscriptions/'.$providerSubscriptionId)
            ->json();
    }

    public function setSubscriptionEnabled(string $providerSubscriptionId, bool $enabled): array
    {
        $action = $enabled ? 'enable' : 'disable';

        return (array) Http::timeout(20)
            ->withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->post($this->baseUrl.'/v1/subscriptions/'.$providerSubscriptionId.'/'.$action)
            ->json();
    }

    public function capabilities(): GatewayCapabilities
    {
        return new GatewayCapabilities(
            provider: 'midtrans',
            apiVersion: 'v1 (Subscription) / v2 (Charge), Core API',
            currency: 'IDR',
            hostedCheckout: GatewayCapabilities::SUPPORTED,
            providerManagedRecurring: GatewayCapabilities::SUPPORTED,
            tokenization: GatewayCapabilities::SUPPORTED,
            retryBehavior: GatewayCapabilities::SUPPORTED,
            cancellation: GatewayCapabilities::SUPPORTED,
            resume: GatewayCapabilities::SUPPORTED,
            refund: GatewayCapabilities::UNKNOWN,
            dispute: GatewayCapabilities::UNKNOWN,
            webhookVerification: 'sha512(order_id+status_code+gross_amount+server_key)',
            idempotency: 'subscription-create limited-period idempotency (documented)',
            sandboxAvailable: true,
            merchantPrerequisites: [
                'Recurring activation by Midtrans Support/Sales',
                'GoPay Tokenization activation for Production and Sandbox',
            ],
            limitations: [
                'Recurring methods currently Card and GoPay Tokenization only',
                'GoPay web linking URL single-access',
            ],
        );
    }

    public function mapStatus(string $providerStatus): SubscriptionState
    {
        // Deterministic mapping from documented Midtrans subscription statuses.
        return match (strtolower($providerStatus)) {
            'created', 'pending' => SubscriptionState::Pending,
            'active' => SubscriptionState::Active,
            'inactive' => SubscriptionState::Canceled,
            // transaction-level statuses seen on notifications:
            'settlement', 'capture' => SubscriptionState::Active,
            'deny', 'cancel' => SubscriptionState::Canceled,
            'expire' => SubscriptionState::Expired,
            default => throw new RuntimeException("Unmapped Midtrans status [{$providerStatus}]."),
        };
    }

    public function verifyAndNormalizeWebhook(string $payload, array $headers): NormalizedBillingEvent
    {
        $body = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status', 'transaction_id'] as $required) {
            if (! isset($body[$required])) {
                throw new RuntimeException("Malformed webhook: missing [{$required}].");
            }
        }

        // sha512(order_id + status_code + gross_amount + server_key)
        $expected = hash('sha512', $body['order_id'].$body['status_code'].$body['gross_amount'].$this->serverKey);
        if (! hash_equals($expected, (string) $body['signature_key'])) {
            throw new RuntimeException('Invalid Midtrans signature.');
        }

        $type = match (strtolower((string) $body['transaction_status'])) {
            'settlement', 'capture' => BillingEventType::PaymentSucceeded,
            'deny', 'cancel' => BillingEventType::SubscriptionCanceled,
            'expire' => BillingEventType::SubscriptionExpired,
            'pending' => BillingEventType::PaymentFailed, // provisional failure until settled
            default => throw new RuntimeException("Unknown Midtrans transaction_status [{$body['transaction_status']}]."),
        };

        return new NormalizedBillingEvent(
            provider: 'midtrans',
            providerEventId: (string) ($body['transaction_id'] ?? $body['order_id']),
            type: $type,
            providerSubscriptionId: isset($body['subscription_id']) ? (string) $body['subscription_id'] : null,
            providerTransactionId: (string) $body['transaction_id'],
            newState: $type === BillingEventType::PaymentSucceeded ? SubscriptionState::Active : null,
            occurredAtIso: (string) ($body['transaction_time'] ?? now()->toIso8601String()),
        );
    }
}
