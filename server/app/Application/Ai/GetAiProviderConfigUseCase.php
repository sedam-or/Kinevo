<?php

namespace App\Application\Ai;

use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;
use App\Domain\Ai\ValueObjects\AiProviderProtocol;

/**
 * Safe provider settings snapshot for the settings UI (TASK-P17-006,
 * extended by TASK-P18-007). Allowed surface: provider, protocol, base_url,
 * model, enabled, configured, a masked (non-reversible) hint, verification
 * metadata and the canonical safe status.
 *
 * Forbidden and impossible by construction here: the raw key, its
 * ciphertext, any authorization header, or upstream error payloads. The
 * persisted `credential_hint` is preferred over decrypting the stored key —
 * reads never touch the secret.
 */
final readonly class GetAiProviderConfigUseCase
{
    public function __construct(
        private AiProviderConfigRepository $configs,
        private AiOrchestrator $ai,
    ) {}

    public function __invoke(): array
    {
        $config = $this->configs->get() ?? AiProviderConfig::defaults();
        $status = $this->ai->status();

        // Canonical state from the SAME mapper as /ai/status (TASK-P17-007):
        // the two endpoints can never disagree.
        return [
            'provider' => $config->provider,
            'protocol' => $config->protocol ?? AiProviderProtocol::defaultFor($config->provider),
            'enabled' => $config->enabled,
            'model' => $config->model,
            'base_url' => $config->baseUrl,
            'configured' => $this->configured($config),
            'has_api_key' => $config->apiKey !== null,
            'api_key_hint' => $config->credentialHint ?? $this->hint($config->apiKey),
            'last_verified_at' => $config->lastVerifiedAt,
            'last_status' => $config->lastStatus,
            'last_error_code' => $config->lastErrorCode,
            'status' => array_merge($status->toArray(), [
                'state' => GetAiProviderStatusUseCase::stateFor($this->configs->get(), $status),
            ]),
            'privacy_ok' => true,
        ];
    }

    /**
     * Configured means: a real provider is selected AND everything it
     * requires is present (a credential when the family needs one).
     */
    private function configured(AiProviderConfig $config): bool
    {
        if ($config->provider === AiProviderConfig::PROVIDER_DISABLED) {
            return false;
        }

        $requiresKey = match ($config->provider) {
            AiProviderConfig::PROVIDER_OPENAI => true,
            default => false,
        };

        return ! $requiresKey || $config->apiKey !== null;
    }

    private function hint(?string $key): ?string
    {
        if ($key === null || strlen($key) <= 4) {
            return null;
        }

        return '…'.substr($key, -4);
    }
}
