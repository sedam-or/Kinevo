<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $task_id
 * @property string $filename
 * @property string $stored_name
 * @property string $disk
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'task_id',
    'filename',
    'stored_name',
    'disk',
    'mime_type',
    'size_bytes',
    'sha256',
])]
final class Attachment extends Model {}
