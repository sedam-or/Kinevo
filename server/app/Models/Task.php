<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $program_id
 * @property int|null $goal_id
 * @property int|null $milestone_id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property int $priority_tier
 * @property int|null $estimated_minutes
 * @property string|null $due_at
 * @property int $progress
 * @property int $version
 * @property int|null $workspace_id
 * @property bool $is_sacred_anchor
 */
#[Fillable([
    'user_id',
    'program_id',
    'goal_id',
    'milestone_id',
    'title',
    'description',
    'status',
    'priority_tier',
    'estimated_minutes',
    'due_at',
    'progress',
    'version',
    'workspace_id',
    'is_sacred_anchor',
])]
class Task extends Model
{
    protected $table = 'tasks';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * @return HasMany<Subtask, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('sequence');
    }
}
