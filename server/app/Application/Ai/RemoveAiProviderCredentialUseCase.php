<?php

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;

/**
 * Remove the persisted credential (TASK-P18-003). The configuration itself
 * (provider/model/base URL/enabled) is preserved; only the secret and its
 * hint are cleared. The provider becomes unconfigured until a new key lands.
 */
final readonly class RemoveAiProviderCredentialUseCase
{
    public function __construct(
        private AiProviderConfigRepository $configs,
        private GetAiProviderConfigUseCase $get,
    ) {}

    public function __invoke(): array
    {
        $existing = $this->configs->get() ?? AiProviderConfig::defaults();

        $this->configs->save(new AiProviderConfig(
            provider: $existing->provider,
            enabled: $existing->enabled,
            model: $existing->model,
            baseUrl: $existing->baseUrl,
            apiKey: null,
            userId: $existing->userId,
            protocol: $existing->protocol,
            credentialHint: null,
            lastVerifiedAt: $existing->lastVerifiedAt,
            lastStatus: $existing->lastStatus,
            lastErrorCode: $existing->lastErrorCode,
        ));

        return $this->get->__invoke();
    }
}
