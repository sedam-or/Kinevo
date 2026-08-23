<?php

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;
use App\Infrastructure\Ai\AiProviderFactory;
use App\Infrastructure\Ai\Providers\DisabledProvider;
use InvalidArgumentException;

/**
 * "Test connection" in the settings UI (TASK-P17-006). Pings the provider
 * using CANDIDATE settings so the user can verify before saving. The API key
 * used for the ping is never returned in the response.
 */
final readonly class TestAiProviderConnectionUseCase
{
    public function __construct(
        private AiProviderConfigRepository $configs,
        private AiProviderFactory $factory,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(array $input): array
    {
        $providerName = $input['provider'] ?? '';
        $allowed = [
            AiProviderConfig::PROVIDER_DISABLED,
            AiProviderConfig::PROVIDER_OLLAMA,
            AiProviderConfig::PROVIDER_OPENAI,
            AiProviderConfig::PROVIDER_MOCK,
        ];
        if (! in_array($providerName, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported AI provider.');
        }

        // Resolve the tested key honoring replace/remove semantics against
        // whatever is currently persisted.
        $existing = $this->configs->get() ?? AiProviderConfig::defaults();
        $apiKey = $existing->apiKey;
        if (isset($input['api_key']) && is_string($input['api_key']) && trim($input['api_key']) !== '') {
            $apiKey = trim($input['api_key']);
        }

        if ($providerName === AiProviderConfig::PROVIDER_DISABLED) {
            return ['status' => (new DisabledProvider)->status()->toArray()];
        }

        try {
            $provider = $this->factory->createFrom(
                $providerName,
                $input['base_url'] ?? $existing->baseUrl,
                $input['model'] ?? $existing->model,
                $providerName === AiProviderConfig::PROVIDER_OPENAI ? $apiKey : null,
            );
        } catch (InvalidArgumentException $e) {
            throw $e;
        }

        return ['status' => $provider->status()->toArray()];
    }
}
