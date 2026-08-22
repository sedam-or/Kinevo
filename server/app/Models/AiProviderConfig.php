<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
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
 */
#[Fillable(['provider', 'enabled', 'model', 'base_url', 'api_key'])]
class AiProviderConfig extends Model
{
    public const SINGLETON_ID = 1;

    protected $hidden = ['api_key'];

    public function freshApiKey(): ?string
    {
        $value = $this->getAttributes()['api_key'] ?? null;
        return $value === null ? null : Crypt::decryptString($value);
    }
}