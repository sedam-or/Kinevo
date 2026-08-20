<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persisted recharge timer session (FR-05).
 *
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $last_resumed_at
 * @property int $accumulated_seconds
 * @property int|null $duration_minutes
 * @property Carbon|null $ended_at
 */
#[Fillable([
    'user_id',
    'status',
    'started_at',
    'last_resumed_at',
    'accumulated_seconds',
    'duration_minutes',
    'ended_at',
])]
class RechargeSession extends Model
{
    protected $casts = [
        'started_at' => 'datetime',
        'last_resumed_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}
