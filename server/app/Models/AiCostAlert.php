<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $kind
 * @property int|null $threshold
 * @property array<string, mixed>|null $context
 * @property Carbon|null $seen_at
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'kind', 'threshold', 'context', 'seen_at'])]
class AiCostAlert extends Model
{
    protected $casts = [
        'context' => 'array',
        'seen_at' => 'datetime',
    ];
}
