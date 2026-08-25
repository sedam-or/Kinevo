<?php

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property array|null $document_json
 * @property string|null $markdown_cache
 * @property string|null $plain_text_cache
 * @property int $version
 * @property int|null $workspace_id
 */
#[Fillable([
    'user_id',
    'title',
    'document_json',
    'markdown_cache',
    'plain_text_cache',
    'version',
    'workspace_id',
])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    protected $table = 'notes';

    protected $casts = [
        'document_json' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
