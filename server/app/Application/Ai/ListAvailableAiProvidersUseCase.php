<?php

namespace App\Application\Ai;

use App\Domain\Ai\Entities\AiProviderConfig;
use App\Domain\Ai\ValueObjects\AiProviderCapabilities;
use App\Domain\Ai\ValueObjects\AiProviderProtocol;

/**
 * Provider catalog for the settings UI (TASK-P18-003/P18-010). Facts come
 * from the static capability matrix — the UI derives its fields from this
 * contract instead of hardcoding per-provider conditionals.
 */
final readonly class ListAvailableAiProvidersUseCase
{
    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        $providers = [];
        foreach (AiProviderCapabilities::all() as $name => $capabilities) {
            if ($name === AiProviderConfig::PROVIDER_DISABLED) {
                continue;
            }

            $providers[] = [
                'id' => $name,
                'protocols' => AiProviderProtocol::allowedFor($name),
                'default_protocol' => AiProviderProtocol::defaultFor($name),
                'requires_api_key' => $capabilities['requires_api_key'],
                'requires_base_url' => $capabilities['requires_base_url'],
                'requires_model' => $capabilities['requires_model'],
                'supports_local' => $capabilities['supports_local'],
                'supports_remote' => $capabilities['supports_remote'],
                'supports_connection_test' => $capabilities['supports_connection_test'],
            ];
        }

        return ['providers' => $providers];
    }
}
