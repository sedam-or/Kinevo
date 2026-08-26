<?php

namespace App\Application\Ai;

use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\Contracts\AiProviderResolver;
use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Ai\Entities\AiRun;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiResponse;

/**
 * Non-mutating AI text generation for an allowed role (SRS FR-60,
 * docs/ai-architecture.md). No domain mutation occurs; structured mutation
 * proposals are the concern of the structured-output features (FR-61/62).
 *
 * TASK-P25-005 — metered: preflight before the provider call, one credit
 * spent on success, and every run audited (proposal_type `text_generation`).
 * CLI/system diagnostics bypass this use case on purpose (ai:smoke).
 */
final readonly class GenerateAiTextUseCase
{
    public function __construct(
        private AiOrchestrator $ai,
        private AiProviderResolver $resolver,
        private AiRunRepository $runs,
        private AiCreditGuard $credits,
    ) {}

    public function __invoke(int $userId, AiRequest $request): AiResponse
    {
        $started = hrtime(true);
        $provider = $this->resolver->resolve($userId);
        $byok = $this->resolver->isUserProvided($userId);
        $requestId = $this->credits->begin($userId, $byok);

        try {
            $response = $this->ai->generate($userId, $request);

            $this->credits->recordSuccess(
                $userId,
                $byok,
                $requestId,
                $response,
                'text_generation',
                null,
                (string) hash('sha256', $request->prompt),
            );

            return $response;
        } catch (AiProviderException $e) {
            $this->runs->record(AiRun::failed(
                $userId,
                $provider->name(),
                $provider->model(),
                'text_generation',
                null,
                null,
                (int) ((hrtime(true) - $started) / 1_000_000),
                AiProviderException::CODE_UNAVAILABLE,
                null,
                $requestId,
            ));

            throw $e;
        }
    }
}
