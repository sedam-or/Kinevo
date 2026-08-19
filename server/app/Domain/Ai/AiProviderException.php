<?php

namespace App\Domain\Ai;

use RuntimeException;

/**
 * AI provider failure. Always catchable: the core application MUST remain
 * operational when the AI provider is unavailable (SRS FR-60, §13.6,
 * docs/ai-architecture.md §AI failure behavior).
 */
final class AiProviderException extends RuntimeException
{
    public const CODE_UNAVAILABLE = 'AI_PROVIDER_UNAVAILABLE';

    public static function unavailable(string $message): self
    {
        return new self($message);
    }
}
