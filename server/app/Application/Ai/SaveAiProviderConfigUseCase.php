<?php

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;
use InvalidArgumentException;

/**
 * Persist provider configuration (TASK-P17-006). API key semantics:
 * replace-only (send a new key) or remove-only (`remove_api_key: true`).
 * A saved key is NEVER echoed back — the response is masked by
 * GetAiProviderConfigUseCase.
 */
final readonly class SaveAiProviderConfigUseCase
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
        $provider = $input['provider'] ?? '';
        $allowed = [
            AiProviderConfig::PROVIDER_DISABLED,
            AiProviderConfig::PROVIDER_OLLAMA,
            AiProviderConfig::PROVIDER_OPENAI,
            AiProviderConfig::PROVIDER_MOCK,
        ];
        if (! in_array($provider, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported AI provider.');
        }

        $existing = $this->configs->get() ?? AiProviderConfig::defaults();

        $apiKey = $existing->apiKey;
        if (($input['remove_api_key'] ?? false) === true) {
            $apiKey = null;
        } elseif (isset($input['api_key']) && is_string($input['api_key']) && trim($input['api_key']) !== '') {
            $apiKey = trim($input['api_key']);
        }

        // OpenAI requires a key unless it was explicitly removed (or kept).
        if ($provider === AiProviderConfig::PROVIDER_OPENAI && $apiKey === null
            && ($input['remove_api_key'] ?? false) !== true) {
            throw new InvalidArgumentException('OpenAI requires an API key.');
        }

        $config = new AiProviderConfig(
            provider: $provider,
            enabled: (bool) ($input['enabled'] ?? true),
            model: isset($input['model']) && trim((string) $input['model']) !== '' ? trim((string) $input['model']) : null,
            baseUrl: isset($input['base_url']) && trim((string) $input['base_url']) !== '' ? rtrim(trim((string) $input['base_url']), '/') : null,
            apiKey: $apiKey,
        );

        $this->configs->save($config);

        return $this->get->__invoke();
    }
}
