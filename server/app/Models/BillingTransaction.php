<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * TASK-P24-008 — payment transaction (safe metadata only; never card data).
 *
 * @property int $id
 * @property int $user_id
 * @property int $billing_subscription_id
 * @property string $provider
 * @property string $provider_transaction_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 * @property Carbon|null $occurred_at
 * @property Carbon|null $created_at
 */
class BillingTransaction extends Model
{
    protected $fillable = [
        'user_id', 'billing_subscription_id', 'provider', 'provider_transaction_id',
        'amount_minor', 'currency', 'status', 'occurred_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id');
    }
}
