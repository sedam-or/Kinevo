<?php

namespace App\Http\Controllers\Api;

use App\Application\Scheduling\MiniPauseUseCase;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Mini Pause (FR-07). Moves all eligible tasks scheduled on the given date to
 * the next day's first feasible slots, preserving constraints. The trigger
 * lives on the Today NOW card; the action is transactional, logged, and
 * explained to the user.
 */
final class MiniPauseController extends Controller
{
    public function __construct(
        private readonly MiniPauseUseCase $miniPause,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->miniPause->__invoke(
                $request->user()->id,
                CarbonImmutable::parse($validator->validated()['date']),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result->toArray(), $result->applied ? 200 : 202);
    }
}
