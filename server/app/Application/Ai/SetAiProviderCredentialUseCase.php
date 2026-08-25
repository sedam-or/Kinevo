<?php

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;
use InvalidArgumentException;

/**
 * Store (or rotate) the runtime credential (TASK-P18-003, P18-022). Rotation
 * is atomic: the repository write replaces the single persisted secret, so
 * the old credential stops existing the moment the new one is saved — two
 * active credentials are impossible by construction.
 */
final readonly class SetAiProviderCredentialUseCase
{
    public function __construct(
        private AiProviderConfigRepository $configs,
        private GetAiProviderConfigUseCase $get,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(array $input): array
    {
        $key = isset($input['api_key']) && is_string($input['api_key']) ? trim($input['api_key']) : '';
        if ($key === '') {
            throw new InvalidArgumentException('An API key is required.');
        }

        $existing = $this->configs->get() ?? AiProviderConfig::defaults();
        // Credential storage is provider-independent (TASK-P18-003): the
        // endpoints remain usable even when the active provider needs no key,
        // so a stored secret survives a provider switch.

        $this->configs->save(new AiProviderConfig(
            provider: $existing->provider,
            enabled: $existing->enabled,
            model: $existing->model,
            baseUrl: $existing->baseUrl,
            apiKey: $key,
            userId: $existing->userId,
            protocol: $existing->protocol,
            credentialHint: SaveAiProviderConfigUseCase::hintFor($key),
            lastVerifiedAt: $existing->lastVerifiedAt,
            lastStatus: $existing->lastStatus,
            lastErrorCode: $existing->lastErrorCode,
        ));

        return $this->get->__invoke();
    }
}
