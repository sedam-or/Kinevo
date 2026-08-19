<?php

namespace App\Http\Controllers\Api;

use App\Application\Recovery\GetRecoveryListUseCase;
use App\Application\Recovery\ResolveRecoveredTaskUseCase;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class RecoveryController extends Controller
{
    public function __construct(
        private readonly GetRecoveryListUseCase $getRecoveryList,
        private readonly ResolveRecoveredTaskUseCase $resolveRecoveredTask,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'recovery' => $this->getRecoveryList->__invoke($request->user()->id),
        ]);
    }

    public function resolve(Request $request, int $taskId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => ['required', 'string', 'in:reschedule,complete,backlog'],
            'due_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $task = $this->resolveRecoveredTask->__invoke(
                $request->user()->id,
                $taskId,
                $data['action'],
                isset($data['due_at']) ? CarbonImmutable::parse($data['due_at']) : null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Task not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['task' => $task->toArray()]);
    }
}
