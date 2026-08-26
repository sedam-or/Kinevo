<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TASK-P24-009 — idempotent billing event log. Raw payloads are NOT stored by
 * default (PII minimization); only a payload hash for replay detection.
 *
 * @property int $id
 * @property string $provider
 * @property string $provider_event_id
 * @property string $event_type
 * @property string $payload_hash
 * @property string $processing_status
 */
class BillingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider', 'provider_event_id', 'event_type', 'payload_hash',
        'received_at', 'processed_at', 'processing_status',
        'processing_attempts', 'last_error_code',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
