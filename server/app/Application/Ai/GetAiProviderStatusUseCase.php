<?php

namespace App\Application\Ai;

use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\ValueObjects\AiProviderStatus;

/**
 * Provider health snapshot for telemetry (SRS §17.8 "AI provider status").
 * Safe metadata only; never private content.
 */
final readonly class GetAiProviderStatusUseCase
{
    public function __construct(
        private AiOrchestrator $ai,
    ) {}

    public function __invoke(): AiProviderStatus
    {
        return $this->ai->status();
    }
}
