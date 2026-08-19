<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $task_id
 * @property string $date
 * @property string $start_at
 * @property string $end_at
 * @property int $duration_minutes
 * @property string $status
 * @property string $source
 * @property int $schedule_version
 * @property bool $locked
 * @property int $version
 * @property string|null $created_at
 * @property string|null $updated_at
 */
#[Fillable([
    'user_id',
    'task_id',
    'date',
    'start_at',
    'end_at',
    'duration_minutes',
    'status',
    'source',
    'schedule_version',
    'locked',
    'version',
])]
class TaskAssignment extends Model
{
    protected $table = 'task_assignments';

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'locked' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
