<?php

namespace App\Http\Controllers\Api;

use App\Application\Analytics\GetWorkLifeAnalyticsUseCase;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Analytics (SRS Phase 13). Consumes already-generated data; business
 * calculations live in the domain/application layer, never in controllers.
 */
final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly GetWorkLifeAnalyticsUseCase $workLife,
    ) {}

    /**
     * Work-Life Ratio analytics (FR-05, TASK-126): productive (focus) and
     * Recharge minutes over a period → normative WorkRatio/RechargeRatio.
     */
    public function workLife(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $validated = $validator->validated();

        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])
            : CarbonImmutable::now();
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])
            : $to->startOfWeek();

        if ($from->gt($to)) {
            return response()->json(['error' => 'from cannot be after to.'], 422);
        }

        return response()->json(
            $this->workLife->__invoke($userId, $from, $to)->toArray(),
            200,
        );
    }
}
