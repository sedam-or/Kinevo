<?php

namespace App\Domain\Billing;

/**
 * TASK-P24-009 — internal subscription state machine (provider-neutral).
 * Adapters map provider statuses deterministically via GatewayCapabilities.
 */
enum SubscriptionState: string
{
    case Pending = 'pending';

    case Active = 'active';

    case PastDue = 'past_due';

    case CancelAtPeriodEnd = 'cancel_at_period_end';

    case Canceled = 'canceled';

    case Expired = 'expired';

    /** States that grant paid entitlements (P23 resolver contract). */
    public function grantsPaidAccess(): bool
    {
        return in_array($this, [self::Active, self::PastDue, self::CancelAtPeriodEnd], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Active, self::Expired, self::Canceled], true),
            self::Active => in_array($target, [self::PastDue, self::CancelAtPeriodEnd, self::Canceled, self::Expired], true),
            self::PastDue => in_array($target, [self::Active, self::Expired, self::Canceled], true),
            self::CancelAtPeriodEnd => in_array($target, [self::Active /* resume */, self::Expired, self::Canceled], true),
            self::Canceled, self::Expired => false, // terminal; new subscription instead
        };
    }
}
