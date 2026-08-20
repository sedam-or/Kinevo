<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Persisted execution timer session (TASK-120).
 *
 * @property int $id
 * @property int $user_id
 * @property int $task_id
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $last_resumed_at
 * @property int $accumulated_seconds
 * @property Carbon|null $ended_at
 */
#[Fillable([
    'user_id',
    'task_id',
    'status',
    'started_at',
    'last_resumed_at',
    'accumulated_seconds',
    'ended_at',
])]
class ExecutionSession extends Model
{
    protected $casts = [
        'started_at' => 'datetime',
        'last_resumed_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
