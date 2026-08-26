<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * TASK-P24-007 — internal subscription row (provider ids kept separate).
 *
 * @property int $id
 * @property int $user_id
 * @property string $plan_code
 * @property int $price_amount_minor
 * @property string $price_currency
 * @property string $provider
 * @property string $operation_id
 * @property string|null $provider_subscription_id
 * @property string $state
 * @property Carbon|null $last_event_at
 * @property bool $uncertain
 */
class BillingSubscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_code', 'price_amount_minor', 'price_currency',
        'provider', 'operation_id', 'provider_subscription_id', 'state',
        'last_event_at', 'uncertain',
    ];

    protected $casts = [
        'last_event_at' => 'datetime',
        'uncertain' => 'boolean',
        'price_amount_minor' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
