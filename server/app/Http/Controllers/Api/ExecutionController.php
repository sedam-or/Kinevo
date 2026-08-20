<?php

namespace App\Http\Controllers\Api;

use App\Application\Execution\AbandonExecutionUseCase;
use App\Application\Execution\CompleteExecutionUseCase;
use App\Application\Execution\GetActiveExecutionUseCase;
use App\Application\Execution\ListExecutionSessionsUseCase;
use App\Application\Execution\PauseExecutionUseCase;
use App\Application\Execution\ResumeExecutionUseCase;
use App\Application\Execution\StartExecutionUseCase;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Execution timer (TASK-120): start/pause/resume/complete/abandon. The timer
 * state is persisted server-side; elapsed time is derived from persisted
 * timestamps (FR-05).
 */
final class ExecutionController extends Controller
{
    public function __construct(
        private readonly StartExecutionUseCase $startExecution,
        private readonly PauseExecutionUseCase $pauseExecution,
        private readonly ResumeExecutionUseCase $resumeExecution,
        private readonly CompleteExecutionUseCase $completeExecution,
        private readonly AbandonExecutionUseCase $abandonExecution,
        private readonly GetActiveExecutionUseCase $getActive,
        private readonly ListExecutionSessionsUseCase $listSessions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'task_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $sessions = $this->listSessions->__invoke(
            $request->user()->id,
            $data['task_id'] ?? null,
            $data['limit'] ?? 50,
        );

        return response()->json([
            'executions' => array_map(
                static fn ($session) => $session->toArray(CarbonImmutable::now()),
                $sessions,
            ),
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $session = $this->getActive->__invoke($request->user()->id);

        return response()->json([
            'execution' => $session?->toArray(CarbonImmutable::now()),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $session = $this->startExecution->__invoke(
                $request->user()->id,
                (int) $validator->validated()['task_id'],
                CarbonImmutable::now(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->mapStartError($e);
        }

        return response()->json(['execution' => $session->toArray(CarbonImmutable::now())], 201);
    }

    public function pause(Request $request, int $sessionId): JsonResponse
    {
        try {
            $session = $this->pauseExecution->__invoke(
                $request->user()->id,
                $sessionId,
                CarbonImmutable::now(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->mapSessionError($e);
        }

        return response()->json(['execution' => $session->toArray(CarbonImmutable::now())]);
    }

    public function resume(Request $request, int $sessionId): JsonResponse
    {
        try {
            $session = $this->resumeExecution->__invoke(
                $request->user()->id,
                $sessionId,
                CarbonImmutable::now(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->mapSessionError($e);
        }

        return response()->json(['execution' => $session->toArray(CarbonImmutable::now())]);
    }

    public function complete(Request $request, int $sessionId): JsonResponse
    {
        try {
            $result = $this->completeExecution->__invoke(
                $request->user()->id,
                $sessionId,
                CarbonImmutable::now(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->mapSessionError($e);
        }

        return response()->json([
            'execution' => $result['execution']->toArray(CarbonImmutable::now()),
            'focus_session' => $result['focus_session']->toArray(),
            'task' => $result['task']->toArray(),
            'continuation' => $result['continuation']?->toArray(),
        ]);
    }

    public function abandon(Request $request, int $sessionId): JsonResponse
    {
        try {
            $session = $this->abandonExecution->__invoke(
                $request->user()->id,
                $sessionId,
                CarbonImmutable::now(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->mapSessionError($e);
        }

        return response()->json(['execution' => $session->toArray(CarbonImmutable::now())]);
    }

    private function mapStartError(InvalidArgumentException $e): JsonResponse
    {
        return match ($e->getMessage()) {
            'Task not found.' => response()->json(['error' => $e->getMessage()], 404),
            'An execution timer is already running.' => response()->json(['error' => $e->getMessage()], 409),
            default => response()->json(['error' => $e->getMessage()], 422),
        };
    }

    private function mapSessionError(InvalidArgumentException $e): JsonResponse
    {
        return match ($e->getMessage()) {
            'Execution session not found.' => response()->json(['error' => $e->getMessage()], 404),
            default => response()->json(['error' => $e->getMessage()], 422),
        };
    }
}
