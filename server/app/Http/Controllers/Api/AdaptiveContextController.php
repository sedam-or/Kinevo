<?php

namespace App\Http\Controllers\Api;

use App\Application\Adaptive\GetBurnoutSignalUseCase;
use App\Application\Adaptive\ListContextCheckInsUseCase;
use App\Application\Adaptive\RecordContextCheckInUseCase;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class AdaptiveContextController extends Controller
{
    public function __construct(
        private readonly RecordContextCheckInUseCase $recordCheckIn,
        private readonly ListContextCheckInsUseCase $listCheckIns,
        private readonly GetBurnoutSignalUseCase $getBurnoutSignal,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $observations = $this->listCheckIns->__invoke(
            $request->user()->id,
            (int) ($validator->validated()['limit'] ?? 50),
        );

        return response()->json([
            'observations' => array_map(static fn ($observation) => $observation->toArray(), $observations),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_id' => ['nullable', 'integer'],
            'energy_level' => ['nullable', 'integer', 'between:1,10'],
            'stress_level' => ['nullable', 'integer', 'between:1,10'],
            'task_difficulty' => ['nullable', 'integer', 'between:1,10'],
            'skill_familiarity' => ['nullable', 'integer', 'between:1,10'],
            'interruption_count' => ['nullable', 'integer', 'min:0'],
            'context_switch_cost' => ['nullable', 'integer', 'min:0'],
            'focus_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'checked_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $observation = $this->recordCheckIn->__invoke(
                $request->user()->id,
                $data['task_id'] ?? null,
                $data['energy_level'] ?? null,
                $data['stress_level'] ?? null,
                $data['task_difficulty'] ?? null,
                $data['skill_familiarity'] ?? null,
                $data['interruption_count'] ?? null,
                $data['context_switch_cost'] ?? null,
                $data['focus_duration_minutes'] ?? null,
                isset($data['checked_at']) ? CarbonImmutable::parse($data['checked_at']) : null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Task not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['observation' => $observation->toArray()], 201);
    }

    public function burnout(Request $request): JsonResponse
    {
        $signal = $this->getBurnoutSignal->__invoke($request->user()->id);

        return response()->json([
            'active' => $signal->active,
            'reason' => $signal->reason,
            'sample_count' => $signal->sampleCount,
        ]);
    }
}
