<?php

namespace App\Domain\Ai;

use RuntimeException;

/**
 * AI returned output that failed structured validation (SRS FR-61, §13.3).
 * Raised BEFORE anything reaches persistence — malformed AI JSON never becomes
 * a domain mutation (FR-61 acceptance criterion). Maps to 422 AI_OUTPUT_INVALID.
 */
final class AiOutputException extends RuntimeException
{
    public const CODE_INVALID = 'AI_OUTPUT_INVALID';

    public static function invalid(string $reason): self
    {
        return new self($reason);
    }
}
