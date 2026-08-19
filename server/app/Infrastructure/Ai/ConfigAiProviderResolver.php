<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Contracts\AiProviderResolver;

/**
 * Config-backed provider resolver. Caches the first resolved provider per
 * application instance; resolution is deferred to first use so the provider is
 * constructed with the configuration current at call time.
 */
final class ConfigAiProviderResolver implements AiProviderResolver
{
    private ?AiProvider $resolved = null;

    public function __construct(
        private readonly AiProviderFactory $factory,
    ) {}

    public function resolve(): AiProvider
    {
        return $this->resolved ??= $this->factory->create();
    }
}
