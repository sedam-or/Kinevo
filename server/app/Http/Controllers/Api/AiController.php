<?php

namespace App\Http\Controllers\Api;

use App\Application\Ai\AcceptAiProposalUseCase;
use App\Application\Ai\AcceptCanvasProposalUseCase;
use App\Application\Ai\AcceptNoteTaskExtractionUseCase;
use App\Application\Ai\GenerateAiTextUseCase;
use App\Application\Ai\GenerateCanvasProposalUseCase;
use App\Application\Ai\GenerateNoteProposalUseCase;
use App\Application\Ai\GenerateValidatedProposalUseCase;
use App\Application\Ai\GetAiProposalUseCase;
use App\Application\Ai\GetAiProviderConfigUseCase;
use App\Application\Ai\GetAiProviderStatusUseCase;
use App\Application\Ai\ListAiProposalsUseCase;
use App\Application\Ai\ListAiRunsUseCase;
use App\Application\Ai\ListAvailableAiProvidersUseCase;
use App\Application\Ai\RejectAiProposalUseCase;
use App\Application\Ai\RemoveAiProviderCredentialUseCase;
use App\Application\Ai\SaveAiProviderConfigUseCase;
use App\Application\Ai\SetAiProviderCredentialUseCase;
use App\Application\Ai\SetAiProviderEnabledUseCase;
use App\Application\Ai\TestAiProviderConnectionUseCase;
use App\Application\Ai\UpdateAiProposalUseCase;
use App\Domain\Ai\AiOutputException;
use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\Entities\AiProposal as AiProposalEntity;
use App\Domain\Ai\ValueObjects\AiProposalType;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiRole;
use App\Domain\Saas\Exceptions\EntitlementLimitException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class AiController extends Controller
{
    public function __construct(
        private readonly GenerateAiTextUseCase $generateText,
        private readonly GenerateValidatedProposalUseCase $generateProposal,
        private readonly GenerateNoteProposalUseCase $generateNoteProposal,
        private readonly GenerateCanvasProposalUseCase $generateCanvasProposal,
        private readonly GetAiProviderStatusUseCase $providerStatus,
        private readonly GetAiProviderConfigUseCase $providerConfig,
        private readonly SaveAiProviderConfigUseCase $saveProviderConfig,
        private readonly SetAiProviderCredentialUseCase $setCredential,
        private readonly RemoveAiProviderCredentialUseCase $removeCredential,
        private readonly SetAiProviderEnabledUseCase $setEnabled,
        private readonly ListAvailableAiProvidersUseCase $listProviders,
        private readonly TestAiProviderConnectionUseCase $testProviderConnection,
        private readonly ListAiRunsUseCase $listRuns,
        private readonly ListAiProposalsUseCase $listProposals,
        private readonly GetAiProposalUseCase $getProposal,
        private readonly AcceptAiProposalUseCase $acceptProposal,
        private readonly AcceptNoteTaskExtractionUseCase $acceptNoteExtraction,
        private readonly AcceptCanvasProposalUseCase $acceptCanvasProposal,
        private readonly RejectAiProposalUseCase $rejectProposal,
        private readonly UpdateAiProposalUseCase $updateProposal,
        private readonly AiProposalRepository $proposalRepository,
    ) {}

    /**
     * TASK-P23-007/P25-004 — metered AI denial is enforced inside the use
     * cases (AiCreditGuard); controllers only translate the domain denial.
     */
    private function deniedEntitlement(EntitlementLimitException $e): JsonResponse
    {
        return response()->json($e->toResponse(), 403);
    }

    public function status(): JsonResponse
    {
        return response()->json(['status' => $this->providerStatus->__invoke()]);
    }

    public function configShow(): JsonResponse
    {
        return response()->json(['config' => $this->providerConfig->__invoke()]);
    }

    public function configUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['required', 'string'],
            'enabled' => ['nullable', 'boolean'],
            'model' => ['nullable', 'string', 'max:128'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'remove_api_key' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $config = $this->saveProviderConfig->__invoke($validator->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['config' => $config]);
    }

    public function configTest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:128'],
            'api_key' => ['nullable', 'string', 'max:4096'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $status = $this->testProviderConnection->__invoke($validator->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($status);
    }

    /**
     * TASK-P18-006 — canonical AI settings surface. The legacy /ai/config
     * endpoints delegate to the exact same use cases; no second source of
     * truth exists.
     */
    public function settingsShow(): JsonResponse
    {
        return $this->configShow();
    }

    public function settingsUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['nullable', 'string'],
            'protocol' => ['nullable', 'string', 'max:32'],
            'enabled' => ['nullable', 'boolean'],
            'model' => ['nullable', 'string', 'max:128'],
            'base_url' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Canonical PATCH may land before a credential exists; the
            // credential endpoints complete the setup (TASK-P18-006).
            $config = $this->saveProviderConfig->__invoke($validator->validated(), allowIncomplete: true);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['config' => $config]);
    }

    public function credentialSet(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'api_key' => ['required', 'string', 'max:4096'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $config = $this->setCredential->__invoke($validator->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['config' => $config]);
    }

    public function credentialRemove(): JsonResponse
    {
        return response()->json(['config' => $this->removeCredential->__invoke()]);
    }

    public function settingsTest(Request $request): JsonResponse
    {
        return $this->configTest($request);
    }

    public function settingsEnable(): JsonResponse
    {
        try {
            $config = ($this->setEnabled)(true);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['config' => $config]);
    }

    public function settingsDisable(): JsonResponse
    {
        try {
            $config = ($this->setEnabled)(false);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['config' => $config]);
    }

    public function providersIndex(): JsonResponse
    {
        return response()->json($this->listProviders->__invoke());
    }

    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => ['required', 'string', 'in:goal_decomposition,milestone_proposal,note_summarization,task_extraction,concept_extraction,knowledge_relation_suggestion,canvas_proposal,natural_language_explanation'],
            'prompt' => ['required', 'string', 'max:'.config('ai.max_prompt_chars', 8000)],
            'system_prompt' => ['nullable', 'string', 'max:'.config('ai.max_system_prompt_chars', 2000)],
            'temperature' => ['nullable', 'numeric', 'between:0,2'],
            'max_tokens' => ['nullable', 'integer', 'between:1,8192'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // TASK-P22-006 — per-user single-flight: one in-flight generation per
        // owner; concurrent requests are rejected instead of piling up cost.
        $lock = Cache::lock('ai:generate:'.$request->user()->id, 60);
        if (! $lock->get()) {
            return response()->json(['error' => 'An AI request is already running.', 'code' => 'AI_CONCURRENCY_LIMIT'], 429);
        }

        try {
            $response = $this->generateText->__invoke(
                $request->user()->id,
                new AiRequest(
                    new AiRole($data['role']),
                    $data['prompt'],
                    $data['system_prompt'] ?? null,
                    isset($data['temperature']) ? (float) $data['temperature'] : null,
                    $data['max_tokens'] ?? null,
                ),
            );
        } catch (EntitlementLimitException $e) {
            return $this->deniedEntitlement($e);
        } catch (AiProviderException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => AiProviderException::CODE_UNAVAILABLE,
            ], 503);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } finally {
            $lock->release();
        }

        return response()->json([
            'text' => $response->text,
            'provider' => $response->provider,
            'model' => $response->model,
            'latency_ms' => $response->latencyMs,
        ]);
    }

    public function proposals(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:goal_breakdown,milestone,task_extraction,canvas,summary'],
            'prompt' => ['required', 'string', 'max:'.config('ai.max_prompt_chars', 8000)],
            'system_prompt' => ['nullable', 'string', 'max:'.config('ai.max_system_prompt_chars', 2000)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $validated = $this->generateProposal->__invoke(
                $request->user()->id,
                new AiProposalType($data['type']),
                $data['prompt'],
                $data['system_prompt'] ?? null,
            );
        } catch (EntitlementLimitException $e) {
            return $this->deniedEntitlement($e);
        } catch (AiProviderException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => AiProviderException::CODE_UNAVAILABLE,
            ], 503);
        } catch (AiOutputException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => AiOutputException::CODE_INVALID,
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $proposal = $this->proposalRepository->persist(AiProposalEntity::pending(
            $request->user()->id,
            new AiProposalType($validated->type->value),
            $validated->schemaVersion,
            $validated->payload,
        ));

        return response()->json(['proposal' => $proposal->toArray()]);
    }

    public function runs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'proposal_type' => ['nullable', 'string', 'in:goal_breakdown,milestone,task_extraction,canvas,summary'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $runs = $this->listRuns->__invoke(
            $request->user()->id,
            $data['proposal_type'] ?? null,
            $data['limit'] ?? 50,
        );

        return response()->json([
            'runs' => array_map(static fn ($run) => $run->toArray(), $runs),
        ]);
    }

    public function proposalsIndex(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'proposal_type' => ['nullable', 'string', 'in:goal_breakdown,milestone,task_extraction,canvas,summary'],
            'decision' => ['nullable', 'string', 'in:pending,accepted,rejected,edited'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $proposals = $this->listProposals->__invoke(
            $request->user()->id,
            $data['proposal_type'] ?? null,
            $data['decision'] ?? null,
            $data['limit'] ?? 50,
        );

        return response()->json([
            'proposals' => array_map(static fn ($p) => $p->toArray(), $proposals),
        ]);
    }

    public function proposalsShow(Request $request, int $proposalId): JsonResponse
    {
        try {
            $proposal = $this->getProposal->__invoke($request->user()->id, $proposalId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['proposal' => $proposal->toArray()]);
    }

    public function proposalsUpdate(Request $request, int $proposalId): JsonResponse
    {
        $payload = $request->json()->all();
        if ($payload === []) {
            return response()->json(['error' => 'A proposal payload object is required.'], 422);
        }
        try {
            $proposal = $this->updateProposal->__invoke($request->user()->id, $proposalId, $payload);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'AI proposal not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['proposal' => $proposal->toArray()]);
    }

    public function proposalsAccept(Request $request, int $proposalId): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $type = $this->getProposal->__invoke($userId, $proposalId)->type;

            if ($type->equals(new AiProposalType(AiProposalType::GOAL_BREAKDOWN))) {
                $milestones = $this->acceptProposal->__invoke($userId, $proposalId);

                return response()->json([
                    'milestones' => array_map(static fn ($m) => $m->toArray(), $milestones),
                ]);
            }

            if ($type->equals(new AiProposalType(AiProposalType::TASK_EXTRACTION))) {
                $tasks = $this->acceptNoteExtraction->__invoke($userId, $proposalId);

                return response()->json([
                    'tasks' => array_map(static fn ($t) => $t->toArray(), $tasks),
                ]);
            }

            if ($type->equals(new AiProposalType(AiProposalType::CANVAS))) {
                $canvas = $this->acceptCanvasProposal->__invoke($userId, $proposalId);

                return response()->json(['canvas' => $canvas->toArray()]);
            }

            return response()->json(['error' => 'This proposal type is not yet supported.'], 422);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'AI proposal not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function summarizeNote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'note_id' => ['required', 'integer'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $proposal = $this->generateNoteProposal->__invoke(
                $request->user()->id,
                $data['note_id'],
                new AiProposalType(AiProposalType::SUMMARY),
                $data['instructions'] ?? null,
            );
        } catch (EntitlementLimitException $e) {
            return $this->deniedEntitlement($e);
        } catch (InvalidArgumentException $e) {
            if (str_starts_with($e->getMessage(), 'Note not found')) {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        } catch (AiProviderException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => AiProviderException::CODE_UNAVAILABLE,
            ], 503);
        } catch (AiOutputException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => AiOutputException::CODE_INVALID,
            ], 422);
        }

        return response()->json(['proposal' => $proposal->toArray()]);
    }

    public function suggestCanvas(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prompt' => ['required', 'string', 'max:'.config('ai.max_prompt_chars', 8000)],
            'system_prompt' => ['nullable', 'string', 'max:'.config('ai.max_system_prompt_chars', 2000)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $proposal = $this->generateCanvasProposal->__invoke(
                $request->user()->id,
                $data['prompt'],
                $data['system_prompt'] ?? null,
            );
        } catch (EntitlementLimitException $e) {
            return $this->deniedEntitlement($e);
        } catch (AiProviderException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => AiProviderException::CODE_UNAVAILABLE,
            ], 503);
        } catch (AiOutputException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => AiOutputException::CODE_INVALID,
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['proposal' => $proposal->toArray()]);
    }

    public function extractTasks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'note_id' => ['required', 'integer'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $proposal = $this->generateNoteProposal->__invoke(
                $request->user()->id,
                $data['note_id'],
                new AiProposalType(AiProposalType::TASK_EXTRACTION),
                $data['instructions'] ?? null,
            );
        } catch (EntitlementLimitException $e) {
            return $this->deniedEntitlement($e);
        } catch (InvalidArgumentException $e) {
            if (str_starts_with($e->getMessage(), 'Note not found')) {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        } catch (AiProviderException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => AiProviderException::CODE_UNAVAILABLE,
            ], 503);
        } catch (AiOutputException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => AiOutputException::CODE_INVALID,
            ], 422);
        }

        return response()->json(['proposal' => $proposal->toArray()]);
    }

    public function proposalsReject(Request $request, int $proposalId): JsonResponse
    {
        try {
            $proposal = $this->rejectProposal->__invoke($request->user()->id, $proposalId);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'AI proposal not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['proposal' => $proposal->toArray()]);
    }
}
