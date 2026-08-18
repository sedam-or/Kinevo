<?php

namespace App\Models;

use Database\Factories\CanvasDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $canvas_id
 * @property int $schema_version
 * @property array $scene_json
 * @property int $version
 */
#[Fillable([
    'canvas_id',
    'schema_version',
    'scene_json',
    'version',
])]
class CanvasDocument extends Model
{
    /** @use HasFactory<CanvasDocumentFactory> */
    use HasFactory;

    protected $table = 'canvas_documents';

    protected $casts = [
        'scene_json' => 'array',
        'schema_version' => 'integer',
    ];

    public function canvas(): BelongsTo
    {
        return $this->belongsTo(Canvas::class);
    }
}
