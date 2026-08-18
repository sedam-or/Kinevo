<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $task_id
 * @property string $title
 * @property string|null $notes
 * @property int $sequence
 * @property bool $completed
 * @property int $version
 */
#[Fillable([
    'user_id',
    'task_id',
    'title',
    'notes',
    'sequence',
    'completed',
    'version',
])]
class Subtask extends Model
{
    protected $table = 'subtasks';

    protected $casts = [
        'completed' => 'boolean',
    ];

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
