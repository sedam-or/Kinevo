<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property string $horizon
 * @property string|null $start_date
 * @property string|null $target_date
 * @property string|null $target_metric
 * @property string $status
 * @property int $priority_tier
 * @property string $progress_mode
 * @property int $progress
 */
#[Fillable([
    'user_id',
    'title',
    'description',
    'horizon',
    'start_date',
    'target_date',
    'target_metric',
    'status',
    'priority_tier',
    'progress_mode',
    'progress',
])]
class Goal extends Model
{
    protected $table = 'goals';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
