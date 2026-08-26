<?php

namespace App\Domain\Billing;

/**
 * TASK-P24-004 — provider-agnostic gateway boundary. Adapters live in
 * Infrastructure\Billing; Domain/Application never import provider SDKs.
 *
 * Capability honesty (§1.2): every method maps to a flag in the adapter's
 * CapabilityMatrix. Unsupported operations throw UnsupportedCapabilityException
 * instead of being faked.
 */
interface PaymentGateway
{
    /** Machine-readable capability matrix for this adapter (P24-003). */
    public function capabilities(): GatewayCapabilities;

    /** Normalize a provider status string into an internal subscription state. */
    public function mapStatus(string $providerStatus): SubscriptionState;

    /** Verify a webhook payload's signature/token; returns normalized event or throws. */
    public function verifyAndNormalizeWebhook(string $payload, array $headers): NormalizedBillingEvent;
}
