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
use App\Domain\Workspaces\Contracts\WorkspaceRepository;
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
        private WorkspaceRepository $workspaces,
        private AiCreditGuard $credits,
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

        // TASK-P19-025/026 — minimal workspace-bounded context: the goal's
        // workspace name/type travels with the prompt so the breakdown stays
        // relevant to that context. Never credentials, never other workspaces.
        $workspaceContext = '';
        if ($goal->workspaceId !== null) {
            $workspace = $this->workspaces->findForUser($goal->userId, $goal->workspaceId);
            if ($workspace !== null) {
                $workspaceContext = " The goal belongs to the \"{$workspace->name}\" workspace"
                    ." (type: {$workspace->type->value}); keep milestone titles relevant to it.";
            }
        }

        // TASK-P17-027: request a concise decision summary + high-level
        // assumptions, inputs used and constraints honoured. The schema forbids
        // chain-of-thought exposure; this keeps the generator aligned.
        // The goal id MUST be supplied so the model can echo it: the schema
        // requires goal_id in the payload and the use case verifies it matches
        // the requested goal (cross-goal guard, FR-52). A real provider can
        // never satisfy that check if it is never told the id.
        return "Break down the goal \"{$goal->title}\" (goal id: {$goal->id}) into between 2 and 5 milestones with target dates (YYYY-MM-DD) and estimated workload in minutes.{$workspaceContext}"
            .' Response MUST be a single JSON object with EXACTLY these keys:'
            .' {"type":"goal_breakdown_proposal","goal_id":'.$goal->id.',"rationale":"short decision summary string",'
            .' "assumptions":["string"],"inputs":["string"],"constraints":["string"],"risks":["string"],'
            .' "milestones":[{"title":"string","target_date":"YYYY-MM-DD","estimated_minutes":number}]}.'
            .' Use ONLY those key names; omit empty optional arrays. Do NOT add keys.'
            .' Do NOT include chain-of-thought or any text outside the JSON object.';
    }

    private function generate(int $userId, int $goalId, string $prompt, ?string $systemPrompt): ValidatedAiProposal
    {
        $started = hrtime(true);
        $type = new AiProposalType(AiProposalType::GOAL_BREAKDOWN);
        $provider = $this->resolver->resolve();
        $contextHash = hash('sha256', $prompt);
        $schemaVersion = $this->registry->versionFor($type);
        $requestId = $this->credits->begin($userId);

        try {
            $response = $this->ai->generate(new AiRequest($type->role(), $prompt, $systemPrompt));

            $proposal = $this->parser->parse($type, $response->text);

            $this->credits->spend($userId);
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
                null,
                1,
                $requestId,
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
