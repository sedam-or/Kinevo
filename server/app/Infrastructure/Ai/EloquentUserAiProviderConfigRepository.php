<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\UserAiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;
use App\Models\UserAiProviderConfig as UserAiProviderConfigModel;
use Illuminate\Support\Facades\Crypt;

final readonly class EloquentUserAiProviderConfigRepository implements UserAiProviderConfigRepository
{
    public function forUser(int $userId): ?AiProviderConfig
    {
        $model = UserAiProviderConfigModel::query()->where('user_id', $userId)->first();
        if ($model === null) {
            return null;
        }

        return new AiProviderConfig(
            provider: $model->provider,
            enabled: (bool) $model->enabled,
            model: $model->model,
            baseUrl: $model->base_url,
            apiKey: $model->freshApiKey(),
            userId: $userId,
        );
    }

    public function save(int $userId, AiProviderConfig $config): void
    {
        $values = [
            'user_id' => $userId,
            'provider' => $config->provider,
            'model' => $config->model,
            'base_url' => $config->baseUrl,
            'enabled' => $config->enabled,
        ];
        if ($config->apiKey !== null) {
            $values['api_key_encrypted'] = Crypt::encryptString($config->apiKey);
        } elseif (! $config->enabled) {
            $values['api_key_encrypted'] = null;
        }

        UserAiProviderConfigModel::query()->updateOrCreate(['user_id' => $userId], $values);
    }

    public function remove(int $userId): void
    {
        UserAiProviderConfigModel::query()->where('user_id', $userId)->delete();
    }
}
