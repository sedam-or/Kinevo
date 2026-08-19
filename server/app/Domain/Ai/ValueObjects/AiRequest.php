<?php

namespace App\Domain\Ai\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable AI generation request (SRS §13.4). Context MUST be minimal and
 * relevant; the prompt is user-owned context for the given role.
 */
final readonly class AiRequest
{
    public function __construct(
        public AiRole $role,
        public string $prompt,
        public ?string $systemPrompt = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
    ) {
        if (trim($this->prompt) === '') {
            throw new InvalidArgumentException('AI prompt cannot be empty.');
        }

        if ($this->temperature !== null && ($this->temperature < 0 || $this->temperature > 2)) {
            throw new InvalidArgumentException('Temperature must be between 0 and 2.');
        }
    }
}
