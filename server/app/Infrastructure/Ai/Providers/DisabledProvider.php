<?php

namespace App\Infrastructure\Ai\Providers;

use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\ValueObjects\AiProviderStatus;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiResponse;

/**
 * Explicit "no AI" selection. Every generation fails with
 * AI_PROVIDER_UNAVAILABLE; the application remains fully operational
 * (SRS FR-60 acceptance criterion).
 */
final readonly class DisabledProvider implements AiProvider
{
    public function name(): string
    {
        return 'disabled';
    }

    public function model(): string
    {
        return 'none';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function generate(AiRequest $request): AiResponse
    {
        throw AiProviderException::unavailable('AI provider is disabled.');
    }

    public function status(): AiProviderStatus
    {
        return new AiProviderStatus($this->name(), $this->model(), false, null, 'AI provider is disabled.');
    }
}
