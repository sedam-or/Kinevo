<?php

namespace App\Application\Billing;

use App\Domain\Billing\BillingEventType;
use App\Domain\Billing\NormalizedBillingEvent;
use App\Domain\Billing\SubscriptionState;
use App\Domain\Saas\Contracts\SubscriptionRepository as SaasSubscriptionRepository;
use App\Domain\Saas\Plan;
use App\Domain\Saas\Subscription;
use App\Infrastructure\Billing\MidtransGateway;
use App\Models\BillingEvent;
use App\Models\BillingSubscription;
use App\Models\BillingTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * TASK-P24-010/011/014/016/021/022/023/024/043 — checkout creation
 * (idempotent, one-active-subscription guard), verified-event application
 * (idempotent, out-of-order safe, entitlement sync), and refund/chargeback
 * recording without silently guessing entitlement.
 */
final class BillingService
{
    public function __construct(
        private MidtransGateway $gateway,
        private SaasSubscriptionRepository $saasSubscriptions,
    ) {}

    /**
     * TASK-P24-010/011 — create a Midtrans subscription for a paid plan.
     * Idempotency: an existing PENDING row for the same user+plan is returned
     * as-is instead of creating a duplicate provider subscription.
     *
     * @return array<string, mixed>
     */
    public function startCheckout(int $userId, string $planCode): array
    {
        if (! Plan::exists($planCode)) {
            throw new InvalidArgumentException("Unknown plan [{$planCode}].");
        }
        if ($planCode === 'free') {
            throw new InvalidArgumentException('The Free plan does not require checkout.');
        }

        $existing = BillingSubscription::query()
            ->where('user_id', $userId)
            ->where('plan_code', $planCode)
            ->where('state', 'pending')
            ->first();
        if ($existing !== null) {
            return $this->present($existing);
        }

        // TASK-P24-043 — web rule: one active subscription per user. Cross-platform
        // (Apple/Google) duplicates need product approval and are NOT enforced here.
        $active = BillingSubscription::query()
            ->where('user_id', $userId)
            ->whereIn('state', [SubscriptionState::Active->value, SubscriptionState::PastDue->value, SubscriptionState::CancelAtPeriodEnd->value])
            ->first();
        if ($active !== null) {
            throw new InvalidArgumentException('ACTIVE_SUBSCRIPTION_EXISTS: cancel the active subscription before checking out another plan.');
        }

        $user = User::query()->findOrFail($userId);
        $price = (array) config("billing.prices.{$planCode}");
        if ($price === []) {
            throw new RuntimeException("No price configured for plan [{$planCode}].");
        }

        $operationId = 'kinevo-'.$userId.'-'.Str::lower(Str::random(10));

        // TASK-P24-005 / COMMERCIAL PRICING DELTA — amount_major IS the price in
        // whole Rupiah (Pro = 49_900, Power = 89_900); Midtrans takes whole IDR
        // so amount = amount_major directly. amount_minor (major * 100) is a
        // DERIVED cent-equivalent stored only for P24 persistence compatibility.
        $priceMajor = (int) $price['amount_major'];
        $payload = [
            'name' => 'Kinevo '.$planCode.' ('.$user->email.')',
            'amount' => $priceMajor,
            'currency' => $price['currency'],
            'payment_type' => 'credit_card',
            'token' => (string) config('billing.midtrans.test_card_token'),
            'schedule' => [
                'interval' => (int) $price['interval_count'],
                'interval_unit' => strtolower((string) $price['interval']),
                'start_time' => now()->addMonth()->format('Y-m-d H:i:s O'),
            ],
            'metadata' => [
                'kinevo_user_id' => $userId,
                'kinevo_plan_code' => $planCode,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
        ];

        $created = $this->gateway->createSubscription($payload);

        $row = BillingSubscription::query()->create([
            'user_id' => $userId,
            'plan_code' => $planCode,
            'price_amount_minor' => $priceMajor * 100,
            'price_currency' => $price['currency'],
            'provider' => 'midtrans',
            'operation_id' => $operationId,
            'provider_subscription_id' => (string) ($created['id'] ?? ''),
            'state' => 'pending',
        ]);

        return $this->present($row, $created);
    }

    /**
     * TASK-P24-013..016/024 — apply one verified, normalized event.
     * Idempotency: unique (provider,event_id). Out-of-order: events older than
     * the last applied timestamp are recorded but never regress state.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    public function applyEvent(NormalizedBillingEvent $event, array $rawPayload = []): string
    {
        $duplicate = BillingEvent::query()
            ->where('provider', $event->provider)
            ->where('provider_event_id', $event->providerEventId)
            ->exists();
        if ($duplicate) {
            return 'duplicate'; // TASK-P24-014 safe no-op
        }

        $row = BillingEvent::query()->create([
            'provider' => $event->provider,
            'provider_event_id' => $event->providerEventId,
            'event_type' => $event->type->value,
            'payload_hash' => hash('sha256', json_encode($rawPayload)),
            'received_at' => now(),
            'processing_status' => 'processed',
        ]);

        $sub = isset($rawPayload['subscription_id'])
            ? BillingSubscription::query()->where('provider_subscription_id', (string) $rawPayload['subscription_id'])->first()
            : null;

        if ($sub === null && isset($rawPayload['metadata'])) {
            $meta = (array) $rawPayload['metadata'];
            $sub = BillingSubscription::query()
                ->where('user_id', (int) ($meta['kinevo_user_id'] ?? 0))
                ->where('plan_code', (string) ($meta['kinevo_plan_code'] ?? '-'))
                ->first();
        }

        if ($sub === null) {
            $row->update(['processing_status' => 'ignored', 'last_error_code' => 'UNMATCHED_SUBSCRIPTION']);

            return 'ignored';
        }

        // TASK-P24-016 — out-of-order protection.
        $occurredAt = CarbonImmutable::parse($event->occurredAtIso);
        if ($sub->last_event_at !== null && $occurredAt->lt(CarbonImmutable::parse($sub->last_event_at))) {
            $row->update(['processing_status' => 'ignored', 'last_error_code' => 'OUT_OF_ORDER']);

            return 'out_of_order';
        }

        // State transition through the explicit machine.
        $target = $this->stateFor($event);
        if ($target !== null) {
            $current = SubscriptionState::from($sub->state);
            if ($current->canTransitionTo($target)) {
                $sub->state = $target->value;
            } elseif ($target === SubscriptionState::Active && $current === SubscriptionState::Pending) {
                $sub->state = $target->value; // first activation from pending
            } else {
                $sub->uncertain = true; // TASK-P24-016 do not guess
                $row->update(['processing_status' => 'failed', 'last_error_code' => 'INVALID_TRANSITION']);

                return 'invalid_transition';
            }
        }
        // TASK-P24-019 — a recovered payment clears prior uncertainty.
        if ($event->type === BillingEventType::PaymentSucceeded && $sub->uncertain) {
            $sub->uncertain = false;
        }
        // TASK-P24-022/023 — a chargeback makes entitlement uncertain (funds
        // reversed); no silent state change. A merchant refund does not touch state.
        if ($event->type === BillingEventType::ChargebackOpened) {
            $sub->uncertain = true;
        }
        $sub->last_event_at = Carbon::parse($occurredAt);
        $sub->save();

        // Payment transaction record (safe metadata only).
        if ($event->type === BillingEventType::PaymentSucceeded || $event->type === BillingEventType::PaymentFailed) {
            BillingTransaction::query()->updateOrCreate(
                ['provider_transaction_id' => $event->providerTransactionId ?? $event->providerEventId],
                [
                    'user_id' => $sub->user_id,
                    'billing_subscription_id' => $sub->id,
                    'provider' => 'midtrans',
                    'amount_minor' => (int) round(((float) ($rawPayload['gross_amount'] ?? 0)) * 100),
                    'currency' => (string) ($rawPayload['currency'] ?? 'IDR'),
                    'status' => $event->type === BillingEventType::PaymentSucceeded ? 'succeeded' : 'failed',
                    'occurred_at' => $occurredAt,
                ],
            );
        }

        // TASK-P24-022/023 — refund / chargeback mirror onto the charged transaction.
        if ($event->type === BillingEventType::RefundCreated || $event->type === BillingEventType::ChargebackOpened) {
            BillingTransaction::query()
                ->where('provider_transaction_id', $event->providerTransactionId ?? $event->providerEventId)
                ->update(['status' => 'refunded']);
        }

        // TASK-P24-024 — entitlement synchronization into the P23 resolver.
        $saas = $this->saasSubscriptions->forUser($sub->user_id);
        $planCode = (string) data_get(json_decode(json_encode($rawPayload), true), 'metadata.kinevo_plan_code', $sub->plan_code);
        $newState = match (true) {
            $target === SubscriptionState::Active => 'active',
            $target === SubscriptionState::Expired => 'expired',
            in_array($event->type, [BillingEventType::SubscriptionCanceled], true) => 'canceled',
            $saas !== null && $saas->isActive() => $saas->state, // keep paid grace states
            default => 'free-fallback',
        };
        $effective = new Subscription(
            userId: $sub->user_id,
            planCode: Plan::exists($planCode) ? $planCode : Plan::defaultCode(),
            provider: 'midtrans',
            state: $newState === 'free-fallback'
                ? (Subscription::STATE_ACTIVE) // free fallback stays active on free tier
                : $newState,
        );
        $this->saasSubscriptions->save($effective);

        return 'applied';
    }

    private function stateFor(NormalizedBillingEvent $event): ?SubscriptionState
    {
        return match ($event->type) {
            BillingEventType::PaymentSucceeded, BillingEventType::SubscriptionActivated,
            BillingEventType::SubscriptionRenewed => SubscriptionState::Active,
            BillingEventType::PaymentFailed => SubscriptionState::PastDue,
            BillingEventType::SubscriptionCanceled => SubscriptionState::Canceled,
            BillingEventType::SubscriptionExpired => SubscriptionState::Expired,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function present(BillingSubscription $row, array $providerResponse = []): array
    {
        return [
            'billing_subscription_id' => $row->id,
            'plan_code' => $row->plan_code,
            'status' => $row->state,
            'provider' => $row->provider,
            'provider_subscription_id' => $row->provider_subscription_id,
            'redirect_url' => $providerResponse['actions']['url'] ?? null,
            'payment_type' => $providerResponse['payment_type'] ?? null,
        ];
    }
}
