<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Contracts\AiProviderResolver;
use App\Domain\Ai\ValueObjects\AiProviderStatus;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiResponse;

/**
 * AI orchestration seam (docs/ai-architecture.md). Selects and routes to the
 * configured provider (resolved lazily at call time). Future context building
 * (SRS §13.4) and audit recording (SRS §7.7) plug in here without changing
 * domain semantics.
 */
final class AiOrchestrator
{
    private ?AiProvider $provider = null;

    public function __construct(
        private readonly AiProviderResolver $resolver,
    ) {}

    public function generate(AiRequest $request): AiResponse
    {
        return $this->provider()->generate($request);
    }

    public function status(): AiProviderStatus
    {
        return $this->provider()->status();
    }

    private function provider(): AiProvider
    {
        return $this->provider ??= $this->resolver->resolve();
    }
}
