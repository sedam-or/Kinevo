<?php

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;
use App\Domain\Ai\ValueObjects\AiProviderCapabilities;
use InvalidArgumentException;

/**
 * Enable/disable the persisted runtime provider (TASK-P18-003). Disabling is
 * a first-class state: configuration is kept, resolution falls back to
 * deployment defaults, and status reports `disabled`.
 */
final readonly class SetAiProviderEnabledUseCase
{
    public function __construct(
        private AiProviderConfigRepository $configs,
        private GetAiProviderConfigUseCase $get,
    ) {}

    public function __invoke(bool $enabled): array
    {
        $existing = $this->configs->get() ?? AiProviderConfig::defaults();

        if ($enabled && $existing->provider === AiProviderConfig::PROVIDER_DISABLED) {
            throw new InvalidArgumentException('Configure a provider before enabling it.');
        }

        if ($enabled && AiProviderCapabilities::for($existing->provider)->requiresApiKey
            && $existing->apiKey === null) {
            throw new InvalidArgumentException('Store a credential before enabling this provider.');
        }

        $this->configs->save(new AiProviderConfig(
            provider: $existing->provider,
            enabled: $enabled,
            model: $existing->model,
            baseUrl: $existing->baseUrl,
            apiKey: $existing->apiKey,
            userId: $existing->userId,
            protocol: $existing->protocol,
            credentialHint: $existing->credentialHint,
            lastVerifiedAt: $existing->lastVerifiedAt,
            lastStatus: $existing->lastStatus,
            lastErrorCode: $existing->lastErrorCode,
        ));

        return $this->get->__invoke();
    }
}
