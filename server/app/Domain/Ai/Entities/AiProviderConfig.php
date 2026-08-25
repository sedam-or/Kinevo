<?php

namespace App\Domain\Ai\Entities;

/**
 * Persisted AI provider configuration (TASK-P17-006, P18-001/P18-002).
 * Single global record on the MVP single-owner seam; user_id is carried for
 * the owner scope key when identities expand.
 *
 * The API key is ALWAYS handled with the encrypted server-side cast; it must
 * never travel raw in any client payload. credential_hint is a safe,
 * non-reversible display hint persisted at save time so reads never decrypt.
 */
final readonly class AiProviderConfig
{
    public function __construct(
        public string $provider,
        public bool $enabled,
        public ?string $model = null,
        public ?string $baseUrl = null,
        public ?string $apiKey = null,
        public ?int $userId = null,
        public ?string $protocol = null,
        public ?string $credentialHint = null,
        public ?string $lastVerifiedAt = null,
        public ?string $lastStatus = null,
        public ?string $lastErrorCode = null,
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
