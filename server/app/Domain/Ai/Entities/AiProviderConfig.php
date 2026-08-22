<?php

namespace App\Domain\Ai\Entities;

/**
 * Persisted AI provider configuration (TASK-P17-006, design.md §104 /
 * "AI Provider settings contract"). Single global record on MVP single-user.
 *
 * The API key is ALWAYS handled with the encrypted server-side cast; it must
 * never travel raw in any client payload.
 */
final readonly class AiProviderConfig
{
    public function __construct(
        public string $provider,
        public bool $enabled,
        public ?string $model = null,
        public ?string $baseUrl = null,
        public ?string $apiKey = null,
    ) {}

    public const PROVIDER_DISABLED = 'disabled';
    public const PROVIDER_OLLAMA = 'ollama';
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_MOCK = 'mock';

    public static function defaults(): self
    {
        return new self(self::PROVIDER_DISABLED, false);
    }
}