<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property string $workload_type
 * @property int|null $weekly_target_minutes
 * @property int|null $min_weekly_minutes
 * @property int|null $max_weekly_minutes
 * @property string $status
 * @property int $priority_tier
 * @property int $version
 */
#[Fillable([
    'user_id',
    'name',
    'description',
    'category',
    'workload_type',
    'weekly_target_minutes',
    'min_weekly_minutes',
    'max_weekly_minutes',
    'status',
    'priority_tier',
    'version',
])]
class Program extends Model
{
    protected $table = 'programs';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
