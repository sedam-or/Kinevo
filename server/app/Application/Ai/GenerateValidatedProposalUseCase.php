<?php

namespace App\Application\Ai;

use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\AiOutputException;
use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\AiSchemaRegistry;
use App\Domain\Ai\Contracts\AiProviderResolver;
use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Ai\Entities\AiRun;
use App\Domain\Ai\StructuredAiOutputParser;
use App\Domain\Ai\ValueObjects\AiProposalType;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\ValidatedAiProposal;
use InvalidArgumentException;

/**
 * Generate and validate a structured AI proposal (SRS FR-61, §13.3).
 *
 * The AI output is parsed and validated against a versioned schema BEFORE
 * anything is returned — malformed AI JSON never reaches persistence as a
 * domain mutation. Every run is audited in ai_runs (SRS §7.7). Proposals are
 * returned for the user's preview/approval decision (FR-62); nothing is
 * applied here.
 */
final readonly class GenerateValidatedProposalUseCase
{
    public function __construct(
        private AiOrchestrator $ai,
        private AiProviderResolver $resolver,
        private AiSchemaRegistry $registry,
        private StructuredAiOutputParser $parser,
        private AiRunRepository $runs,
        private AiCreditGuard $credits,
        private AiCostEstimator $costEstimator,
    ) {}

    public function __invoke(
        int $userId,
        AiProposalType $type,
        string $prompt,
        ?string $systemPrompt = null,
    ): ValidatedAiProposal {
        $started = hrtime(true);
        $provider = $this->resolver->resolve();
        $contextHash = hash('sha256', $prompt);
        $schemaVersion = $this->registry->versionFor($type);
        $requestId = $this->credits->begin($userId);

        try {
            $response = $this->ai->generate(new AiRequest($type->role(), $prompt, $systemPrompt));

            $proposal = $this->parser->parse($type, $response->text);

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
                $type->value,
                $schemaVersion,
                null,
                $contextHash,
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

            return $proposal;
        } catch (AiProviderException $e) {
            $this->recordFailure($userId, $provider->name(), $provider->model(), $type, $schemaVersion, $contextHash, $started, AiProviderException::CODE_UNAVAILABLE, $requestId);

            throw $e;
        } catch (AiOutputException $e) {
            $this->recordFailure($userId, $provider->name(), $provider->model(), $type, $schemaVersion, $contextHash, $started, AiOutputException::CODE_INVALID, $requestId);

            throw $e;
        } catch (InvalidArgumentException $e) {
            $this->recordFailure($userId, $provider->name(), $provider->model(), $type, $schemaVersion, $contextHash, $started, AiOutputException::CODE_INVALID, $requestId);

            throw $e;
        }
    }

    private function recordFailure(
        int $userId,
        string $providerName,
        string $providerModel,
        AiProposalType $type,
        int $schemaVersion,
        string $contextHash,
        int $started,
        string $errorCode,
        ?string $requestId = null,
    ): void {
        $this->runs->record(AiRun::failed(
            $userId,
            $providerName,
            $providerModel,
            $type->value,
            $schemaVersion,
            $contextHash,
            (int) ((hrtime(true) - $started) / 1_000_000),
            $errorCode,
            null,
            $requestId,
        ));
    }
}
