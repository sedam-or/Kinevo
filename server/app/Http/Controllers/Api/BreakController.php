<?php

namespace App\Http\Controllers\Api;

use App\Application\Breaks\EndBreakUseCase;
use App\Application\Breaks\StartBreakUseCase;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Break Mode (FR-36/FR-39). Confirms a manual break period (start/end date
 * range) and ends an active break, producing the break summary. The covered
 * weeks are tagged exceptional for capacity feedback (FR-49) and EOD prompts
 * are suppressed for the duration (FR-47 Business Rules).
 */
final class BreakController extends Controller
{
    public function __construct(
        private readonly StartBreakUseCase $startBreak,
        private readonly EndBreakUseCase $endBreak,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $result = $this->startBreak->__invoke(
                $request->user()->id,
                CarbonImmutable::parse($data['start_date']),
                CarbonImmutable::parse($data['end_date']),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result->toArray(), 200);
    }

    public function end(Request $request): JsonResponse
    {
        $result = $this->endBreak->__invoke($request->user()->id);

        return response()->json($result->toArray(), $result->applied ? 200 : 202);
    }
}
