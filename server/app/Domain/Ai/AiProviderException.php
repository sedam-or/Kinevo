<?php

namespace App\Domain\Ai;

use RuntimeException;

/**
 * AI provider failure. Always catchable: the core application MUST remain
 * operational when the AI provider is unavailable (SRS FR-60, §13.6,
 * docs/ai-architecture.md §AI failure behavior).
 *
 * Stable, safe error codes (TASK-P18-008) are surfaced to clients instead of
 * raw upstream payloads; the message stays human-readable and never leaks
 * provider internals or credentials.
 */
final class AiProviderException extends RuntimeException
{
    public const CODE_UNAVAILABLE = 'AI_PROVIDER_UNAVAILABLE';

    public const CODE_AUTH_FAILED = 'AI_PROVIDER_AUTH_FAILED';

    public const CODE_BAD_CONFIGURATION = 'AI_PROVIDER_BAD_CONFIGURATION';

    public const CODE_MODEL_NOT_FOUND = 'AI_PROVIDER_MODEL_NOT_FOUND';

    public const CODE_TIMEOUT = 'AI_PROVIDER_TIMEOUT';

    public const CODE_RATE_LIMITED = 'AI_PROVIDER_RATE_LIMITED';

    public const CODE_UNSUPPORTED = 'AI_PROVIDER_UNSUPPORTED';

    /**
     * Stable, client-facing error code. Kept OUT of RuntimeException::$code
     * (which is an int channel for previous-exception chaining).
     */
    private string $stableCode;

    private function __construct(string $message, string $stableCode)
    {
        parent::__construct($message);
        $this->stableCode = $stableCode;
    }

    public static function unavailable(string $message): self
    {
        return new self($message, self::CODE_UNAVAILABLE);
    }

    public static function authFailed(string $message = 'AI provider rejected the credential.'): self
    {
        return new self($message, self::CODE_AUTH_FAILED);
    }

    public static function badConfiguration(string $message): self
    {
        return new self($message, self::CODE_BAD_CONFIGURATION);
    }

    public static function modelNotFound(string $message = 'The configured model was not found on the endpoint.'): self
    {
        return new self($message, self::CODE_MODEL_NOT_FOUND);
    }

    public static function timeout(string $message = 'AI provider did not respond in time.'): self
    {
        return new self($message, self::CODE_TIMEOUT);
    }

    public static function rateLimited(string $message = 'AI provider is rate limiting requests.'): self
    {
        return new self($message, self::CODE_RATE_LIMITED);
    }

    public static function unsupported(string $message): self
    {
        return new self($message, self::CODE_UNSUPPORTED);
    }

    public function errorCode(): string
    {
        return $this->stableCode;
    }
}
