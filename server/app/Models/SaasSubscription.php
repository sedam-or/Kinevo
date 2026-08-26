<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TASK-P23-006 — persisted subscription state (provider-neutral until P24).
 *
 * @property int $id
 * @property int $user_id
 * @property string $plan_code
 * @property string $provider
 * @property string|null $provider_customer_id
 * @property string|null $provider_subscription_id
 * @property string $state
 */
class SaasSubscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id', 'plan_code', 'provider', 'provider_customer_id',
        'provider_subscription_id', 'state',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
