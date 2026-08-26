<?php

namespace App\Http\Controllers\Api;

use App\Application\Ai\CreateGoalBreakdownProposalUseCase;
use App\Application\Goals\CreateGoalUseCase;
use App\Application\Goals\GetGoalUseCase;
use App\Application\Goals\ListGoalsUseCase;
use App\Application\Goals\SetGoalStatusUseCase;
use App\Application\Goals\UpdateGoalUseCase;
use App\Application\Workspaces\ResolveWorkspaceContext;
use App\Domain\Ai\AiOutputException;
use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\AiRuntimeLimitException;
use App\Domain\Goals\ValueObjects\GoalHorizon;
use App\Domain\Goals\ValueObjects\GoalStatus;
use App\Domain\Saas\Exceptions\EntitlementLimitException;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class GoalController extends Controller
{
    public function __construct(
        private readonly CreateGoalUseCase $createGoalUseCase,
        private readonly ListGoalsUseCase $listGoalsUseCase,
        private readonly GetGoalUseCase $getGoalUseCase,
        private readonly UpdateGoalUseCase $updateGoalUseCase,
        private readonly SetGoalStatusUseCase $setGoalStatusUseCase,
        private readonly CreateGoalBreakdownProposalUseCase $createBreakdownProposalUseCase,
        private readonly ResolveWorkspaceContext $workspaceContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            // TASK-P19-011 — explicit workspace filter; absent = global view.
            $workspaceId = $this->workspaceContext->forList($request->user()->id, $request->query('workspace_id'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        $goals = array_map(
            fn ($goal) => $goal->toArray(),
            $this->listGoalsUseCase->__invoke($request->user()->id, $workspaceId),
        );

        return response()->json(['goals' => $goals]);
    }

    public function show(Request $request, int $goalId): JsonResponse
    {
        try {
            $goal = $this->getGoalUseCase->__invoke($request->user()->id, $goalId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['goal' => $goal->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'description' => ['nullable', 'string'],
            'horizon' => ['required', 'string', 'in:yearly,quarterly,monthly,custom'],
            'start_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'target_metric' => ['nullable', 'string', 'max:100'],
            'priority_tier' => ['sometimes', 'integer', 'between:1,3'],
            'workspace_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            // TASK-P19-011 — explicit context wins; absent → default workspace.
            $workspaceId = $this->workspaceContext->forWrite($request->user()->id, $data['workspace_id'] ?? null);
            $goal = $this->createGoalUseCase->__invoke(
                $request->user()->id,
                $data['title'],
                $data['description'] ?? null,
                new GoalHorizon($data['horizon']),
                isset($data['start_date']) ? CarbonImmutable::parse($data['start_date']) : null,
                isset($data['target_date']) ? CarbonImmutable::parse($data['target_date']) : null,
                $data['target_metric'] ?? null,
                $data['priority_tier'] ?? 3,
                $workspaceId,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['goal' => $goal->toArray()], 201);
    }

    public function update(Request $request, int $goalId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'min:1', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'horizon' => ['sometimes', 'string', 'in:yearly,quarterly,monthly,custom'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'target_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'target_metric' => ['sometimes', 'nullable', 'string', 'max:100'],
            'priority_tier' => ['sometimes', 'integer', 'between:1,3'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $goal = $this->updateGoalUseCase->__invoke(
                $request->user()->id,
                $goalId,
                $data['title'] ?? null,
                $data['description'] ?? null,
                isset($data['horizon']) ? new GoalHorizon($data['horizon']) : null,
                isset($data['start_date']) ? CarbonImmutable::parse($data['start_date']) : null,
                isset($data['target_date']) ? CarbonImmutable::parse($data['target_date']) : null,
                $data['target_metric'] ?? null,
                $data['priority_tier'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Goal not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['goal' => $goal->toArray()]);
    }

    public function status(Request $request, int $goalId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:draft,active,paused,completed,archived,dropped'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $goal = $this->setGoalStatusUseCase->__invoke(
                $request->user()->id,
                $goalId,
                new GoalStatus($validator->validated()['status']),
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Goal not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['goal' => $goal->toArray()]);
    }

    public function breakdown(Request $request, int $goalId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'instructions' => ['nullable', 'string', 'max:'.config('ai.max_prompt_chars', 8000)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $instructions = $validator->validated()['instructions'] ?? '';

        try {
            $proposal = $this->createBreakdownProposalUseCase->__invoke(
                $request->user()->id,
                $goalId,
                $instructions,
            );
        } catch (EntitlementLimitException $e) {
            return response()->json($e->toResponse(), 403);
        } catch (AiRuntimeLimitException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => $e->runtimeCode,
                ...$e->context,
            ], 429);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Goal not found.' || $e->getMessage() === 'AI proposal not found.') {
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
}
