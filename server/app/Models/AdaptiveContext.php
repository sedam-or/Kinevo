<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $task_id
 * @property int|null $energy_level
 * @property int|null $stress_level
 * @property int|null $task_difficulty
 * @property int|null $skill_familiarity
 * @property int|null $interruption_count
 * @property int|null $context_switch_cost
 * @property int|null $focus_duration_minutes
 * @property Carbon $checked_at
 */
#[Fillable([
    'user_id',
    'task_id',
    'energy_level',
    'stress_level',
    'task_difficulty',
    'skill_familiarity',
    'interruption_count',
    'context_switch_cost',
    'focus_duration_minutes',
    'checked_at',
])]
class AdaptiveContext extends Model
{
    protected $table = 'adaptive_context';

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
