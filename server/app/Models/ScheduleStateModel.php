<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-user schedule review state (ADR-016 §2.3).
 *
 * @property int $user_id
 * @property bool $needs_review
 * @property array<string, mixed>|null $reasons
 * @property Carbon|null $impacted_at
 * @property int $last_reviewed_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ScheduleStateModel extends Model
{
    protected $table = 'schedule_states';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'needs_review',
        'reasons',
        'impacted_at',
        'last_reviewed_version',
    ];

    protected $casts = [
        'needs_review' => 'boolean',
        'reasons' => 'array',
        'impacted_at' => 'datetime',
        'last_reviewed_version' => 'integer',
    ];
}
