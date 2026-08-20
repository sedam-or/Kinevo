<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Persisted confirmed Break Mode period (SRS FR-36/FR-39; `break_periods`).
 *
 * @property int $id
 * @property int $user_id
 * @property string $start_date
 * @property string $end_date
 * @property string $status
 * @property string|null $created_at
 * @property string|null $updated_at
 */
#[Fillable([
    'user_id',
    'start_date',
    'end_date',
    'status',
])]
class BreakPeriod extends Model
{
    protected $casts = [
        'start_date' => 'datetime:Y-m-d',
        'end_date' => 'datetime:Y-m-d',
    ];
}
