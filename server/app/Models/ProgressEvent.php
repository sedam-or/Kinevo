<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $event_type
 * @property string $entity_type
 * @property int $entity_id
 * @property string|null $title
 * @property Carbon|null $occurred_at
 * @property string|null $operation_id
 * @property array|null $payload
 */
#[Fillable([
    'user_id',
    'event_type',
    'entity_type',
    'entity_id',
    'title',
    'occurred_at',
    'operation_id',
    'payload',
])]
class ProgressEvent extends Model
{
    protected $casts = [
        'occurred_at' => 'datetime',
        'payload' => 'array',
    ];
}
