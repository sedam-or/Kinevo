<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persisted weekly planning draft (ADR-016). Safe payload only — draft
 * placements; never note contents or AI prompts.
 *
 * @property int $id
 * @property int $user_id
 * @property string $source
 * @property string $status
 * @property array<string, mixed> $payload
 * @property int $base_version
 * @property string $horizon_from
 * @property string $horizon_to
 * @property string|null $generated_for_week
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ScheduleDraftModel extends Model
{
    protected $table = 'schedule_drafts';

    protected $fillable = [
        'user_id',
        'source',
        'status',
        'payload',
        'base_version',
        'horizon_from',
        'horizon_to',
        'generated_for_week',
    ];

    protected $casts = [
        'payload' => 'array',
        'base_version' => 'integer',
    ];
}
