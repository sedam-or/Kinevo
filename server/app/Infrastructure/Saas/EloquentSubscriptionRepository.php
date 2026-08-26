<?php

namespace App\Infrastructure\Saas;

use App\Domain\Saas\Contracts\SubscriptionRepository as Contract;
use App\Domain\Saas\Subscription;
use App\Models\SaasSubscription;

final readonly class EloquentSubscriptionRepository implements Contract
{
    public function forUser(int $userId): ?Subscription
    {
        $model = SaasSubscription::query()->where('user_id', $userId)->first();

        return $model === null ? null : new Subscription(
            userId: (int) $model->user_id,
            planCode: (string) $model->plan_code,
            provider: (string) $model->provider,
            state: (string) $model->state,
            providerCustomerId: $model->provider_customer_id,
            providerSubscriptionId: $model->provider_subscription_id,
        );
    }

    public function save(Subscription $subscription): Subscription
    {
        SaasSubscription::updateOrCreate(
            ['user_id' => $subscription->userId],
            [
                'plan_code' => $subscription->planCode,
                'provider' => $subscription->provider,
                'state' => $subscription->state,
                'provider_customer_id' => $subscription->providerCustomerId,
                'provider_subscription_id' => $subscription->providerSubscriptionId,
            ],
        );

        return $subscription;
    }
}
