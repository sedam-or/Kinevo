<?php

namespace App\Domain\Billing;

/** Normalized provider event produced by a verified webhook (TASK-P24-015). */
final readonly class NormalizedBillingEvent
{
    public function __construct(
        public string $provider,
        public string $providerEventId,
        public BillingEventType $type,
        public ?string $providerSubscriptionId,
        public ?string $providerTransactionId,
        public ?SubscriptionState $newState,
        public string $occurredAtIso,
    ) {}
}
