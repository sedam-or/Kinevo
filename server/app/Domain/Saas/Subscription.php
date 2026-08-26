<?php

namespace App\Domain\Saas;

/**
 * TASK-P23-006 — provider-neutral subscription state abstraction.
 */
final readonly class Subscription
{
    public const STATE_ACTIVE = 'active';

    public const STATE_PAST_DUE = 'past_due';

    public const STATE_CANCELED = 'canceled';

    public const STATE_EXPIRED = 'expired';

    public function __construct(
        public int $userId,
        public string $planCode,
        public string $provider,
        public string $state,
        public ?string $providerCustomerId = null,
        public ?string $providerSubscriptionId = null,
    ) {}

    public static function default(int $userId): self
    {
        return new self($userId, Plan::defaultCode(), 'manual', self::STATE_ACTIVE);
    }

    /** Only an ACTIVE subscription grants paid entitlements. */
    public function isActive(): bool
    {
        return $this->state === self::STATE_ACTIVE;
    }

    /** Effective plan code: non-active subscriptions fall back to the free plan. */
    public function effectivePlanCode(): string
    {
        // Tier retired from the catalog (e.g. legacy rows) degrades to the
        // default plan instead of throwing — plan data is config-owned.
        return $this->isActive() && Plan::exists($this->planCode)
            ? $this->planCode
            : Plan::defaultCode();
    }
}
