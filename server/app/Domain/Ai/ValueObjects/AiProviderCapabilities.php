<?php

namespace App\Domain\Ai\ValueObjects;

/**
 * Static capability matrix per provider family (TASK-P18-001). The settings
 * UI derives its fields from these facts instead of scattered hardcoded
 * conditions; validation uses the same source of truth.
 */
final readonly class AiProviderCapabilities
{
    private function __construct(
        public bool $requiresApiKey,
        public bool $requiresBaseUrl,
        public bool $requiresModel,
        public bool $supportsLocal,
        public bool $supportsRemote,
        public bool $supportsConnectionTest,
    ) {}

    public static function for(string $provider): self
    {
        return match ($provider) {
            'disabled' => new self(false, false, false, false, false, false),
            'mock' => new self(false, false, false, true, false, true),
            'ollama' => new self(false, false, false, true, true, true),
            'openai' => new self(true, false, false, false, true, true),
            default => new self(false, false, false, false, false, false),
        };
    }

    /** @return array<string, array<string, bool>> */
    public static function all(): array
    {
        $providers = ['disabled', 'mock', 'ollama', 'openai'];

        return array_combine($providers, array_map(
            static fn (string $provider) => self::for($provider)->toArray(),
            $providers,
        ));
    }

    /** @return array<string, bool> */
    public function toArray(): array
    {
        return [
            'requires_api_key' => $this->requiresApiKey,
            'requires_base_url' => $this->requiresBaseUrl,
            'requires_model' => $this->requiresModel,
            'supports_local' => $this->supportsLocal,
            'supports_remote' => $this->supportsRemote,
            'supports_connection_test' => $this->supportsConnectionTest,
        ];
    }
}
