<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $hard_landscape_event_id
 * @property string $type
 * @property string $effective_from
 * @property string $effective_to
 * @property string $override_start_at
 * @property string $override_end_at
 * @property string|null $reason
 * @property string|null $created_at
 * @property string|null $updated_at
 */
#[Fillable([
    'user_id',
    'hard_landscape_event_id',
    'type',
    'effective_from',
    'effective_to',
    'override_start_at',
    'override_end_at',
    'reason',
])]
class ScheduleOverride extends Model
{
    protected $table = 'schedule_overrides';

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'override_start_at' => 'datetime',
            'override_end_at' => 'datetime',
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
     * @return BelongsTo<HardLandscapeEvent, $this>
     */
    public function hardLandscapeEvent(): BelongsTo
    {
        return $this->belongsTo(HardLandscapeEvent::class);
    }
}
