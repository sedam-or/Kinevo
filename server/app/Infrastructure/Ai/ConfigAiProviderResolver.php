<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Contracts\AiProviderResolver;
use App\Domain\Ai\Entities\AiProviderConfig;

/**
 * Provider resolver honoring the persisted settings seam (TASK-P17-006):
 * a saved, enabled, non-disabled provider configuration wins; otherwise the
 * env-backed factory is consulted so deployment defaults still apply. The
 * API key is decrypted only inside the resolver and never exposed.
 */
final class ConfigAiProviderResolver implements AiProviderResolver
{
    private ?AiProvider $resolved = null;

    public function __construct(
        private readonly AiProviderFactory $factory,
        private readonly ?AiProviderConfigRepository $configs = null,
    ) {}

    public function resolve(): AiProvider
    {
        return $this->resolved ??= $this->build();
    }

    private function build(): AiProvider
    {
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
}