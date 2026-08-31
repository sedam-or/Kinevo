<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * ADR-017 §2.4 — offline operation ledger row (idempotency record).
 * Stores a payload hash and a bounded result only — never full content.
 *
 * @property int $id
 * @property int $user_id
 * @property string $operation_id
 * @property string $operation_type
 * @property string $entity_type
 * @property int|null $entity_id
 * @property string $payload_hash
 * @property string $status
 * @property array<string, mixed>|null $result
 * @property Carbon|null $created_at
 * @property Carbon|null $processed_at
 */
final class OfflineOperationModel extends Model
{
    protected $table = 'offline_operations';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'operation_id',
        'operation_type',
        'entity_type',
        'entity_id',
        'payload_hash',
        'status',
        'result',
        'created_at',
        'processed_at',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'result' => 'array',
        'created_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
