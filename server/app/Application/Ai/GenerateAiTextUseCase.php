<?php

namespace App\Application\Ai;

use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiResponse;

/**
 * Non-mutating AI text generation for an allowed role (SRS FR-60,
 * docs/ai-architecture.md). No domain mutation occurs; structured mutation
 * proposals are the concern of the structured-output features (FR-61/62).
 */
final readonly class GenerateAiTextUseCase
{
    public function __construct(
        private AiOrchestrator $ai,
    ) {}

    public function __invoke(AiRequest $request): AiResponse
    {
        return $this->ai->generate($request);
    }
}
