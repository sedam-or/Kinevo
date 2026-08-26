<?php

namespace App\Application\Saas;

use App\Domain\Saas\Contracts\SubscriptionRepository;
use App\Domain\Saas\Plan;
use App\Domain\Saas\Subscription;
use InvalidArgumentException;

/**
 * TASK-P23-006/008 — self-serve plan selection on the manual provider.
 * P24 billing will replace/augment this with provider-driven transitions;
 * the state machine (active grants entitlements, else degrades to free)
 * stays identical.
 */
final readonly class SetPlanUseCase
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
    ) {}

    public function __invoke(int $userId, string $planCode): Subscription
    {
        if (! Plan::exists($planCode)) {
            throw new InvalidArgumentException("Unknown plan [{$planCode}].");
        }

        $existing = $this->subscriptions->forUser($userId);

        $subscription = new Subscription(
            userId: $userId,
            planCode: $planCode,
            provider: 'manual',
            state: Subscription::STATE_ACTIVE,
        );

        if ($existing !== null && $existing->state === Subscription::STATE_PAST_DUE) {
            // Past-due accounts may only return to their previous plan via
            // billing recovery (P24); switching plans now would bypass dunning.
            throw new InvalidArgumentException('Billing issue must be resolved before changing plans.');
        }

        return $this->subscriptions->save($subscription);
    }
}
