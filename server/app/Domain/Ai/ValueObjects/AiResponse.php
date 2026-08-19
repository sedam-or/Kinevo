<?php

namespace App\Domain\Ai\ValueObjects;

/**
 * Provider generation result. Transport-level metadata only — never private
 * note content or prompts (SRS §7.7 AI audit / privacy).
 */
final readonly class AiResponse
{
    public function __construct(
        public string $text,
        public string $provider,
        public string $model,
        public int $latencyMs,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {}
}
