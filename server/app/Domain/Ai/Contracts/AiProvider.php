<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\ValueObjects\AiProviderStatus;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiResponse;

/**
 * AI provider abstraction (SRS FR-60, NFR-11; docs/ai-architecture.md).
 * Ollama, external OpenAI-compatible providers, a mock, or a disabled provider
 * are interchangeable without changing domain semantics.
 */
interface AiProvider
{
    public function name(): string;

    public function model(): string;

    public function isAvailable(): bool;

    /**
     * @throws AiProviderException when the provider is unavailable
     */
    public function generate(AiRequest $request): AiResponse;

    public function status(): AiProviderStatus;
}
