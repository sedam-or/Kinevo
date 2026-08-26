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
        private AiCostEstimator $costEstimator,
    ) {}

    public function __invoke(int $userId, AiRequest $request): AiResponse
    {
        $started = hrtime(true);
        $provider = $this->resolver->resolve();
        $requestId = $this->credits->begin($userId);

        try {
            $response = $this->ai->generate($request);

            $this->credits->spend($userId);
            $cost = $this->costEstimator->estimate(
                $response->provider,
                $response->model,
                $response->promptTokens,
                $response->completionTokens,
            );
            $this->runs->record(AiRun::success(
                $userId,
                $response->provider,
                $response->model,
                'text_generation',
                null,
                null,
                null,
                $response->promptTokens,
                $response->completionTokens,
                $response->latencyMs,
                null,
                1,
                $requestId,
                $cost['estimated_cost_minor'],
                $cost['cost_currency'],
                $cost['pricing_source'],
                $cost['pricing_snapshot_id'],
            ));

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
