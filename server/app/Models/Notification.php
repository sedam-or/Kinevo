<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property Carbon|null $scheduled_for
 * @property string|null $title
 * @property array|null $payload
 * @property Carbon|null $read_at
 */
#[Fillable([
    'user_id',
    'type',
    'scheduled_for',
    'title',
    'payload',
    'read_at',
])]
class Notification extends Model
{
    protected $casts = [
        'scheduled_for' => 'date',
        'payload' => 'array',
        'read_at' => 'datetime',
    ];
}
