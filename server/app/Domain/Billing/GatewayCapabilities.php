<?php

namespace App\Domain\Billing;

/**
 * TASK-P24-003 — machine-readable capability flags for one gateway adapter.
 * UNKNOWN is explicit; adapters must never claim unverified support.
 */
final readonly class GatewayCapabilities
{
    public const SUPPORTED = 'SUPPORTED';

    public const UNSUPPORTED = 'UNSUPPORTED';

    public const UNKNOWN = 'UNKNOWN';

    public function __construct(
        public string $provider,
        public string $apiVersion,
        public string $currency,
        public string $hostedCheckout,      // SUPPORTED|UNSUPPORTED|UNKNOWN
        public string $providerManagedRecurring,
        public string $tokenization,
        public string $retryBehavior,
        public string $cancellation,
        public string $resume,
        public string $refund,
        public string $dispute,
        public string $webhookVerification, // e.g. 'sha512-signature-key' | 'static-callback-token'
        public string $idempotency,
        public bool $sandboxAvailable,
        public array $merchantPrerequisites = [],
        public array $limitations = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'api_version' => $this->apiVersion,
            'currency' => $this->currency,
            'hosted_checkout' => $this->hostedCheckout,
            'provider_managed_recurring' => $this->providerManagedRecurring,
            'tokenization' => $this->tokenization,
            'retry_behavior' => $this->retryBehavior,
            'cancellation' => $this->cancellation,
            'resume' => $this->resume,
            'refund' => $this->refund,
            'dispute' => $this->dispute,
            'webhook_verification' => $this->webhookVerification,
            'idempotency' => $this->idempotency,
            'sandbox_available' => $this->sandboxAvailable,
            'merchant_prerequisites' => $this->merchantPrerequisites,
            'limitations' => $this->limitations,
        ];
    }
}
