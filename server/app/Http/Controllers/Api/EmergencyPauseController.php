<?php

namespace App\Http\Controllers\Api;

use App\Application\Scheduling\EmergencyPauseUseCase;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Emergency Pause (FR-07). Tags the week as exceptional, keeps the selected
 * tasks in place, and shifts all other eligible tasks +1 week to feasible
 * slots while preserving constraints. The trigger lives on the Today NOW card;
 * the action is transactional, logged, tagged as a pause event, and explained.
 */
final class EmergencyPauseController extends Controller
{
    public function __construct(
        private readonly EmergencyPauseUseCase $emergencyPause,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date'],
            'keep_task_ids' => ['present', 'array'],
            'keep_task_ids.*' => ['integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $result = $this->emergencyPause->__invoke(
                $request->user()->id,
                CarbonImmutable::parse($data['date']),
                array_map('intval', $data['keep_task_ids']),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result->toArray(), $result->applied ? 200 : 202);
    }
}
