<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $filename
 * @property string $status
 * @property float|null $confidence
 * @property array $rows
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'type',
    'filename',
    'status',
    'confidence',
    'rows',
])]
final class Import extends Model
{
    protected function casts(): array
    {
        return [
            'rows' => 'array',
            'confidence' => 'float',
        ];
    }
}
