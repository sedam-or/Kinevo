<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;
use App\Models\AiProviderConfig as AiProviderConfigModel;
use Illuminate\Support\Facades\Crypt;

final readonly class EloquentAiProviderConfigRepository implements AiProviderConfigRepository
{
    public function get(): ?AiProviderConfig
    {
        $model = AiProviderConfigModel::query()->first();
        if ($model === null) {
            return null;
        }
        return new AiProviderConfig(
            $model->provider,
            (bool) $model->enabled,
            $model->model,
            $model->base_url,
            $model->freshApiKey(),
        );
    }

    public function save(AiProviderConfig $config): void
    {
        $values = [
            'provider' => $config->provider,
            'enabled' => $config->enabled,
            'model' => $config->model,
            'base_url' => $config->baseUrl,
        ];
        if ($config->apiKey !== null) {
            // Encrypted server-side; never stored in plaintext (design §104).
            $values['api_key'] = Crypt::encryptString($config->apiKey);
        } else {
            $values['api_key'] = null;
        }
        // Enforce the single-row contract explicitly. `id` is intentionally
        // not fillable, so updateOrCreate(['id' => …]) cannot match on an
        // empty table and would silently insert a sequence-assigned row
        // instead of bootstrapping the singleton.
        $model = AiProviderConfigModel::query()->firstOrNew();
        if (! $model->exists) {
            $model->id = AiProviderConfigModel::SINGLETON_ID;
        }
        foreach ($values as $key => $value) {
            $model->{$key} = $value;
        }
        $model->save();
    }
}