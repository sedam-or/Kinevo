<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $type
 * @property string $start_at
 * @property string $end_at
 * @property string|null $recurrence
 * @property string|null $created_at
 * @property string|null $updated_at
 */
#[Fillable([
    'user_id',
    'title',
    'type',
    'start_at',
    'end_at',
    'recurrence',
])]
class HardLandscapeEvent extends Model
{
    protected $table = 'hard_landscape_events';

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
