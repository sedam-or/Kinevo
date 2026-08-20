<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Persisted pause event (SRS §7.1 `pause_events`; FR-07).
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $week_start
 * @property string $week_end
 * @property array $keep_task_ids
 * @property array $moved_task_ids
 * @property array $conflict_task_ids
 * @property int $schedule_version
 * @property string|null $created_at
 * @property string|null $updated_at
 */
#[Fillable([
    'user_id',
    'type',
    'week_start',
    'week_end',
    'keep_task_ids',
    'moved_task_ids',
    'conflict_task_ids',
    'schedule_version',
])]
class PauseEvent extends Model
{
    protected $casts = [
        'keep_task_ids' => 'array',
        'moved_task_ids' => 'array',
        'conflict_task_ids' => 'array',
    ];
}
