<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Persisted workspace (TASK-P19-001/002). Single-owner context container;
 * slug is unique per user (composite unique index); exactly one default per
 * user is enforced by the repository transactionally.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $accent
 * @property string $type
 * @property bool $is_default
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Workspace extends Model
{
    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'icon', 'accent',
        'type', 'is_default', 'status',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
