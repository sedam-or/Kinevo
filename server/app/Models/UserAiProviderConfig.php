<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-user BYOK AI provider configuration (TASK-P25-008). One row per user;
 * the API key is encrypted at rest and never serialized raw.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string|null $model
 * @property string|null $base_url
 * @property string|null $api_key_encrypted
 * @property bool $enabled
 */
#[Fillable(['user_id', 'provider', 'model', 'base_url', 'api_key_encrypted', 'enabled'])]
class UserAiProviderConfig extends Model
{
    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function freshApiKey(): ?string
    {
        $value = $this->getAttributes()['api_key_encrypted'] ?? null;

        return $value === null ? null : Crypt::decryptString($value);
    }
}
