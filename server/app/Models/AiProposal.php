<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $proposal_type
 * @property int $schema_version
 * @property array $payload
 * @property string $validation_result
 * @property string $decision
 * @property string|null $operation_id
 * @property Carbon $created_at
 */
#[Fillable([
    'user_id',
    'proposal_type',
    'schema_version',
    'payload',
    'validation_result',
    'decision',
    'operation_id',
])]
class AiProposal extends Model
{
    protected $casts = [
        'payload' => 'array',
    ];

    public const DECISION_PENDING = 'pending';

    public const DECISION_ACCEPTED = 'accepted';

    public const DECISION_REJECTED = 'rejected';

    public const DECISION_EDITED = 'edited';
}
