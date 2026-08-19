<?php

namespace App\Http\Controllers\Api;

use App\Application\Observability\GetHealthUseCase;
use App\Application\Observability\GetMetricsUseCase;
use App\Application\Observability\ListSchedulerRunsUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Observability endpoints (SRS §16.5 minimum telemetry). Public `/health` for
 * the proxy/orchestrator readiness; authenticated `/metrics` and `/runs` for
 * the SRS §16.5 telemetry snapshot. Safe metadata only — never sensitive
 * content.
 */
final class HealthController extends Controller
{
    public function __construct(
        private readonly GetHealthUseCase $health,
        private readonly GetMetricsUseCase $metrics,
        private readonly ListSchedulerRunsUseCase $listRuns,
    ) {}

    public function health(): JsonResponse
    {
        if (! $this->health->healthy()) {
            return response()->json(['status' => 'degraded', 'database' => ['healthy' => false]], 503);
        }

        return response()->json($this->health->snapshot());
    }

    public function metrics(): JsonResponse
    {
        return response()->json(['metrics' => $this->metrics->__invoke()]);
    }

    public function runs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $runs = $this->listRuns->__invoke((int) ($validator->validated()['limit'] ?? 20));

        return response()->json([
            'runs' => array_map(static fn ($run) => $run->toArray(), $runs),
        ]);
    }
}
