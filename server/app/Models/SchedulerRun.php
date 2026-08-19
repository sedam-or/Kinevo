<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $job
 * @property string $status
 * @property int $duration_ms
 * @property string|null $error
 * @property Carbon $started_at
 */
#[Fillable([
    'user_id',
    'job',
    'status',
    'duration_ms',
    'error',
    'started_at',
])]
class SchedulerRun extends Model
{
    protected $casts = [
        'started_at' => 'datetime',
    ];
}
