<?php

namespace App\Domain\Saas\Contracts;

use App\Domain\Saas\Subscription;

interface SubscriptionRepository
{
    public function forUser(int $userId): ?Subscription;

    public function save(Subscription $subscription): Subscription;
}
