<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Single-row persisted AI provider configuration (TASK-P17-006). The API key
 * is encrypted at rest and never serialized raw: the encrypted attribute is
 * excluded from toArray/toJson by default.
 *
 * @property int $id
 * @property string $provider
 * @property bool $enabled
 * @property string|null $model
 * @property string|null $base_url
 * @property string|null $api_key
 * @property int|null $user_id
 * @property string|null $protocol
 * @property string|null $credential_hint
 * @property Carbon|null $last_verified_at
 * @property string|null $last_status
 * @property string|null $last_error_code
 */
#[Fillable(['provider', 'enabled', 'model', 'base_url', 'api_key'])]
class AiProviderConfig extends Model
{
    public const SINGLETON_ID = 1;

    protected $hidden = ['api_key'];

    protected $casts = [
        'enabled' => 'boolean',
        'last_verified_at' => 'datetime',
    ];

    public function freshApiKey(): ?string
    {
        $value = $this->getAttributes()['api_key'] ?? null;

        return $value === null ? null : Crypt::decryptString($value);
    }
}
