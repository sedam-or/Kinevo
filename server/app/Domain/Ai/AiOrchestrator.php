<?php

namespace App\Domain\Ai;

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
    public function __construct(
        private readonly AiProviderResolver $resolver,
    ) {}

    /**
     * TASK-P25-008 — user-scoped routing: per-user BYOK credential wins over
     * the global (Kinevo-hosted) provider. `null` used by CLI/system paths.
     */
    public function generate(int $userId, AiRequest $request): AiResponse
    {
        return $this->resolver->resolve($userId)->generate($request);
    }

    public function status(): AiProviderStatus
    {
        return $this->resolver->resolve(0)->status();
    }
}
