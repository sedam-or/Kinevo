<?php

namespace App\Domain\Ai\ValueObjects;

use App\Domain\Ai\Entities\AiProviderConfig;

/**
 * Explicit wire-protocol capability (TASK-P18-021): OpenAI-compatible
 * endpoints do not all expose identical transport contracts, so the protocol
 * is stored with the settings instead of being implied by the family.
 *
 * Only protocols that actually have a bound adapter are represented.
 */
final readonly class AiProviderProtocol
{
    public const NONE = 'none';

    public const OPENAI_CHAT = 'openai-chat';

    public const OLLAMA_NATIVE = 'ollama';

    public const MOCK = 'mock';

    /** @return list<string> */
    public static function allowedFor(string $provider): array
    {
        return match ($provider) {
            AiProviderConfig::PROVIDER_DISABLED => [self::NONE],
            AiProviderConfig::PROVIDER_MOCK => [self::MOCK],
            AiProviderConfig::PROVIDER_OLLAMA => [self::OLLAMA_NATIVE],
            AiProviderConfig::PROVIDER_OPENAI => [self::OPENAI_CHAT],
            default => [self::NONE],
        };
    }

    public static function defaultFor(string $provider): string
    {
        return self::allowedFor($provider)[0];
    }

    public static function isValid(string $provider, string $protocol): bool
    {
        return in_array($protocol, self::allowedFor($provider), true);
    }
}
