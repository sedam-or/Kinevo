<?php

namespace App\Models;

use Database\Factories\CanvasFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $canvas_id
 * @property string $storage_path
 * @property string $content_type
 * @property int $size_bytes
 * @property string|null $sha256
 */
#[Fillable([
    'canvas_id',
    'storage_path',
    'content_type',
    'size_bytes',
    'sha256',
])]
class CanvasFile extends Model
{
    /** @use HasFactory<CanvasFileFactory> */
    use HasFactory;

    protected $table = 'canvas_files';

    public function canvas(): BelongsTo
    {
        return $this->belongsTo(Canvas::class);
    }
}
