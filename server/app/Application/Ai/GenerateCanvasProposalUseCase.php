<?php

namespace App\Application\Ai;

use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\AiOutputException;
use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\AiSchemaRegistry;
use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\Contracts\AiProviderResolver;
use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Ai\Entities\AiProposal;
use App\Domain\Ai\Entities\AiRun;
use App\Domain\Ai\StructuredAiOutputParser;
use App\Domain\Ai\ValueObjects\AiProposalType;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\ValidatedAiProposal;
use InvalidArgumentException;

/**
 * Generate a canvas generation proposal (SRS §13.3 CanvasProposal, FR-62).
 * The user prompt is the minimal context; output is schema-validated (FR-61)
 * and stored as PENDING. Accept creates the Canvas — never here.
 */
final readonly class GenerateCanvasProposalUseCase
{
    public function __construct(
        private AiOrchestrator $ai,
        private AiProviderResolver $resolver,
        private AiSchemaRegistry $registry,
        private StructuredAiOutputParser $parser,
        private AiRunRepository $runs,
        private AiProposalRepository $proposals,
        private AiCreditGuard $credits,
        private AiCostEstimator $costEstimator,
    ) {}

    public function __invoke(int $userId, string $prompt, ?string $systemPrompt = null): AiProposal
    {
        $type = new AiProposalType(AiProposalType::CANVAS);

        $validated = $this->generate($userId, $type, $prompt, $systemPrompt);

        return $this->proposals->persist(AiProposal::pending(
            $userId,
            $type,
            $validated->schemaVersion,
            $validated->payload,
        ));
    }

    private function generate(int $userId, AiProposalType $type, string $prompt, ?string $systemPrompt): ValidatedAiProposal
    {
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
        } catch (AiOutputException|InvalidArgumentException $e) {
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
