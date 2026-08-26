<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $model
 * @property string $proposal_type
 * @property int|null $schema_version
 * @property string|null $prompt_template_version
 * @property string|null $context_hash
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property int $credits_consumed
 * @property int|null $estimated_cost_minor
 * @property string|null $cost_currency
 * @property string $pricing_source
 * @property string|null $pricing_snapshot_id
 * @property string|null $request_id
 * @property string $status
 * @property int $latency_ms
 * @property string|null $error_code
 * @property Carbon $created_at
 */
#[Fillable([
    'request_id',
    'user_id',
    'provider',
    'model',
    'proposal_type',
    'schema_version',
    'prompt_template_version',
    'context_hash',
    'input_tokens',
    'output_tokens',
    'credits_consumed',
    'estimated_cost_minor',
    'cost_currency',
    'pricing_source',
    'pricing_snapshot_id',
    'status',
    'latency_ms',
    'error_code',
])]
class AiRun extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';
}
