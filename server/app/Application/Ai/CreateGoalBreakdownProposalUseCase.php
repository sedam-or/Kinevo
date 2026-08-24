<?php

namespace App\Application\Ai;

use App\Application\Goals\GetGoalUseCase;
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
use App\Domain\Goals\Goal;
use InvalidArgumentException;

/**
 * Create a goal breakdown proposal (FR-52, FR-62).
 *
 * Validates goal ownership, generates a schema-validated proposal, and
 * persists it as PENDING for the user's accept/edit/reject decision. Nothing
 * is applied here — no large hierarchy is silently committed before user
 * approval (FR-52 postcondition). The goal_id in the validated payload MUST
 * match the requested goal to avoid cross-goal application.
 */
final readonly class CreateGoalBreakdownProposalUseCase
{
    public function __construct(
        private GetGoalUseCase $getGoal,
        private AiOrchestrator $ai,
        private AiProviderResolver $resolver,
        private AiSchemaRegistry $registry,
        private StructuredAiOutputParser $parser,
        private AiRunRepository $runs,
        private AiProposalRepository $proposals,
    ) {}

    public function __invoke(
        int $userId,
        int $goalId,
        string $prompt,
        ?string $systemPrompt = null,
    ): AiProposal {
        $goal = $this->getGoal->__invoke($userId, $goalId);

        $prompt = $this->buildPrompt($goal, $prompt);

        $proposal = $this->generate($userId, $goalId, $prompt, $systemPrompt);

        if (($proposal->payload['goal_id'] ?? null) !== $goalId) {
            throw AiOutputException::invalid('Proposal goal_id does not match the requested goal.');
        }

        return $this->proposals->persist(AiProposal::pending(
            $userId,
            new AiProposalType(AiProposalType::GOAL_BREAKDOWN),
            $proposal->schemaVersion,
            $proposal->payload,
        ));
    }

    private function buildPrompt(Goal $goal, string $instructions): string
    {
        if (trim($instructions) !== '') {
            return $instructions;
        }

        // TASK-P17-027: request a concise decision summary + high-level
        // assumptions, inputs used and constraints honoured. The schema forbids
        // chain-of-thought exposure; this keeps the generator aligned.
        return "Break down the goal \"{$goal->title}\" into milestones with target dates and estimated workload."
            .' Explain the breakdown concisely: a decision summary, the key '
            .'assumptions you made, the inputs you used (deadline, capacity, '
            .'commitments), and the constraints you honoured.'
            .' Do NOT include chain-of-thought.'
            .' Return JSON matching the goal_breakdown_proposal schema.'
            .' Type must be "goal_breakdown_proposal".';
    }

    private function generate(int $userId, int $goalId, string $prompt, ?string $systemPrompt): ValidatedAiProposal
    {
        $started = hrtime(true);
        $type = new AiProposalType(AiProposalType::GOAL_BREAKDOWN);
        $provider = $this->resolver->resolve();
        $contextHash = hash('sha256', $prompt);
        $schemaVersion = $this->registry->versionFor($type);

        try {
            $response = $this->ai->generate(new AiRequest($type->role(), $prompt, $systemPrompt));

            $proposal = $this->parser->parse($type, $response->text);

            $this->runs->record(AiRun::success($userId,
                $response->provider,
                $response->model,
                $type->value,
                $schemaVersion,
                null,
                $contextHash,
                $response->promptTokens,
                $response->completionTokens,
                $response->latencyMs,
            ));

            return $proposal;
        } catch (AiProviderException $e) {
            $this->recordFailure($userId, $provider->name(), $provider->model(), $type, $schemaVersion, $contextHash, $started, AiProviderException::CODE_UNAVAILABLE);

            throw $e;
        } catch (AiOutputException|InvalidArgumentException $e) {
            $this->recordFailure($userId, $provider->name(), $provider->model(), $type, $schemaVersion, $contextHash, $started, AiOutputException::CODE_INVALID);

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
        ));
    }
}
