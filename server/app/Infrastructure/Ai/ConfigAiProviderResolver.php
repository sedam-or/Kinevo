<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Contracts\AiProviderResolver;
use App\Domain\Ai\Contracts\UserAiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;

/**
 * Provider resolver honoring per-user BYOK then the persisted global seam
 * (TASK-P17-006/P25-008): for a user with an enabled BYOK credential, that
 * credential wins (and no ai_credits are charged — the billing split lives in
 * the credit guard); otherwise a saved, enabled global config wins; otherwise
 * the env-backed factory applies deployment defaults. The API key is decrypted
 * only inside the resolver and never exposed. NOT cached — resolution can
 * differ per user.
 */
final class ConfigAiProviderResolver implements AiProviderResolver
{
    public function __construct(
        private readonly AiProviderFactory $factory,
        private readonly ?AiProviderConfigRepository $configs = null,
        private readonly ?UserAiProviderConfigRepository $userConfigs = null,
    ) {}

    public function resolve(int $userId): AiProvider
    {
        $byok = $this->byokFor($userId);
        if ($byok !== null && $byok->enabled && $byok->provider !== AiProviderConfig::PROVIDER_DISABLED) {
            return $this->factory->createFrom(
                $byok->provider,
                $byok->baseUrl,
                $byok->model,
                $byok->apiKey,
            );
        }

        $config = $this->configs?->get();
        if ($config !== null && $config->enabled && $config->provider !== AiProviderConfig::PROVIDER_DISABLED) {
            return $this->factory->createFrom(
                $config->provider,
                $config->baseUrl,
                $config->model,
                $config->apiKey,
            );
        }

        return $this->factory->create();
    }

    public function isUserProvided(int $userId): bool
    {
        $byok = $this->byokFor($userId);

        return $byok !== null
            && $byok->enabled
            && $byok->provider !== AiProviderConfig::PROVIDER_DISABLED;
    }

    private function byokFor(int $userId): ?AiProviderConfig
    {
        return $userId >= 1 ? $this->userConfigs?->forUser($userId) : null;
    }
}
