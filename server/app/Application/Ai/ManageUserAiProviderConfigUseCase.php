<?php

namespace App\Application\Ai;

use App\Application\Saas\EntitlementService;
use App\Domain\Ai\Contracts\UserAiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;
use InvalidArgumentException;

/**
 * TASK-P25-008 — per-user BYOK provider lifecycle. Gated by the `custom_provider`
 * plan entitlement (config/saas.php — product data, per-plan). The API key is
 * encrypted by the repository at rest and never returned in reads (a masked
 * hint only is exposed by the controller projection).
 */
final readonly class ManageUserAiProviderConfigUseCase
{
    private const ALLOWED = [AiProviderConfig::PROVIDER_OLLAMA, AiProviderConfig::PROVIDER_OPENAI];

    public function __construct(
        private UserAiProviderConfigRepository $configs,
        private EntitlementService $entitlements,
    ) {}

    public function get(int $userId): ?AiProviderConfig
    {
        return $this->configs->forUser($userId);
    }

    public function save(
        int $userId,
        string $provider,
        ?string $model,
        ?string $baseUrl,
        ?string $apiKey,
    ): AiProviderConfig {
        $this->entitlements->assertCan($userId, 'custom_provider', 'Bringing your own AI provider is not available on your current plan.');

        if (! in_array($provider, self::ALLOWED, true)) {
            throw new InvalidArgumentException("Unsupported BYOK provider: {$provider}");
        }
        if ($provider === AiProviderConfig::PROVIDER_OPENAI && trim((string) $apiKey) === '') {
            throw new InvalidArgumentException('An API key is required for an OpenAI-compatible BYOK provider.');
        }

        $config = new AiProviderConfig(
            provider: $provider,
            enabled: true,
            model: trim((string) $model) !== '' ? $model : null,
            baseUrl: trim((string) $baseUrl) !== '' ? $baseUrl : null,
            apiKey: $apiKey,
            userId: $userId,
        );

        $this->configs->save($userId, $config);

        return $config;
    }

    public function remove(int $userId): void
    {
        $this->entitlements->assertCan($userId, 'custom_provider', 'Bringing your own AI provider is not available on your current plan.');

        $this->configs->remove($userId);
    }
}
