<?php

namespace App\Models;

use Database\Factories\CanvasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property int|null $goal_id
 * @property int|null $milestone_id
 * @property int|null $program_id
 * @property int|null $task_id
 * @property int $version
 * @property string|null $archived_at
 */
#[Fillable([
    'user_id',
    'title',
    'goal_id',
    'milestone_id',
    'program_id',
    'task_id',
    'version',
    'archived_at',
])]
class Canvas extends Model
{
    /** @use HasFactory<CanvasFactory> */
    use HasFactory;

    protected $table = 'canvases';

    protected $casts = [
        'goal_id' => 'integer',
        'milestone_id' => 'integer',
        'program_id' => 'integer',
        'task_id' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): HasOne
    {
        return $this->hasOne(CanvasDocument::class);
    }
}
