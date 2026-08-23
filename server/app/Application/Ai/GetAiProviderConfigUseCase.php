<?php

namespace App\Application\Ai;

use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;

/**
 * Masked provider configuration snapshot for the settings UI (TASK-P17-006).
 * NEVER returns a raw API key; only `has_api_key` + a last-4 hint.
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
            'enabled' => $config->enabled,
            'model' => $config->model,
            'base_url' => $config->baseUrl,
            'has_api_key' => $config->apiKey !== null,
            'api_key_hint' => $this->hint($config->apiKey),
            'status' => array_merge($status->toArray(), [
                'state' => GetAiProviderStatusUseCase::stateFor($this->configs->get(), $status),
            ]),
            'privacy_ok' => true,
        ];
    }

    private function hint(?string $key): ?string
    {
        if ($key === null || strlen($key) <= 4) {
            return null;
        }
        return '…'.substr($key, -4);
    }
}