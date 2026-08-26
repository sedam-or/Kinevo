<?php

namespace App\Domain\Billing;

/**
 * TASK-P24-015 — normalized internal billing events. Only events relevant to
 * the selected provider (Midtrans) are implemented now.
 */
enum BillingEventType: string
{
    case PaymentSucceeded = 'payment.succeeded';

    case PaymentFailed = 'payment.failed';

    case SubscriptionActivated = 'subscription.activated';

    case SubscriptionRenewed = 'subscription.renewed';

    case SubscriptionCanceled = 'subscription.canceled';

    case SubscriptionExpired = 'subscription.expired';

    case RefundCreated = 'refund.created';

    case ChargebackOpened = 'chargeback.opened';

    case ChargebackResolved = 'chargeback.resolved';
}
