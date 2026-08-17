<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $goal_id
 * @property string $title
 * @property string|null $description
 * @property int $sequence
 * @property string|null $target_date
 * @property int|null $estimated_minutes
 * @property string $status
 * @property string $progress_mode
 * @property int $progress
 * @property string|null $completed_at
 * @property int $version
 */
#[Fillable([
    'user_id',
    'goal_id',
    'title',
    'description',
    'sequence',
    'target_date',
    'estimated_minutes',
    'status',
    'progress_mode',
    'progress',
    'completed_at',
    'version',
])]
class Milestone extends Model
{
    protected $table = 'milestones';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}
