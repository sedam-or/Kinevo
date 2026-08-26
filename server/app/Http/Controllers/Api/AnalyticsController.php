<?php

namespace App\Http\Controllers\Api;

use App\Application\Analytics\GetActivityAnalyticsUseCase;
use App\Application\Analytics\GetCapacityAnalyticsUseCase;
use App\Application\Analytics\GetFocusAnalyticsUseCase;
use App\Application\Analytics\GetGoalProgressAnalyticsUseCase;
use App\Application\Analytics\GetHeatmapAnalyticsUseCase;
use App\Application\Analytics\GetPillarAnalyticsUseCase;
use App\Application\Analytics\GetProgressEventsAnalyticsUseCase;
use App\Application\Analytics\GetTaskCompletionAnalyticsUseCase;
use App\Application\Analytics\GetWorkLifeAnalyticsUseCase;
use App\Application\Workspaces\ResolveWorkspaceContext;
use App\Domain\Analytics\Pillar;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Analytics (SRS Phase 13, TASK-130). Consumes already-generated data through
 * read-side services; business calculations live in the domain/application
 * layer, never in controllers.
 */
final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly GetWorkLifeAnalyticsUseCase $workLife,
        private readonly GetTaskCompletionAnalyticsUseCase $taskCompletion,
        private readonly GetGoalProgressAnalyticsUseCase $goalProgress,
        private readonly GetCapacityAnalyticsUseCase $capacity,
        private readonly GetActivityAnalyticsUseCase $activity,
        private readonly GetFocusAnalyticsUseCase $focus,
        private readonly GetProgressEventsAnalyticsUseCase $progressEvents,
        private readonly GetPillarAnalyticsUseCase $pillars,
        private readonly GetHeatmapAnalyticsUseCase $heatmap,
        private readonly ResolveWorkspaceContext $workspaceContext,
    ) {}

    /**
     * Work-Life Ratio analytics (FR-05, TASK-126): productive (focus) and
     * Recharge minutes over a period → normative WorkRatio/RechargeRatio.
     */
    public function workLife(Request $request): JsonResponse
    {
        $parsed = $this->parseRange($request);
        if ($parsed instanceof JsonResponse) {
            return $parsed;
        }
        [$userId, $from, $to] = $parsed;

        return response()->json($this->workLife->__invoke($userId, $from, $to)->toArray(), 200);
    }

    /**
     * Analytics overview (TASK-130): all read models for the period in one
     * response — task completion, goal progress, capacity, activity, focus,
     * progress events, and the Work-Life Ratio.
     */
    public function overview(Request $request): JsonResponse
    {
        $parsed = $this->parseRange($request);
        if ($parsed instanceof JsonResponse) {
            return $parsed;
        }
        [$userId, $from, $to] = $parsed;
        try {
            // TASK-P19-027 — explicit workspace scoping; absent = global view.
            $wid = $this->workspaceContext->forList($userId, $request->query('workspace_id'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'workspace_id' => $wid,
            'work_life' => $this->workLife->__invoke($userId, $from, $to)->toArray(),
            'task_completion' => $this->taskCompletion->__invoke($userId, $from, $to, $wid)->toArray(),
            'goal_progress' => $this->goalProgress->__invoke($userId, $to, $wid)->toArray(),
            'capacity' => $this->capacity->__invoke($userId, $from, $to)->toArray(),
            'activity' => $this->activity->__invoke($userId, $from, $to)->toArray(),
            'focus' => $this->focus->__invoke($userId, $from, $to)->toArray(),
            'progress_events' => $this->progressEvents->__invoke($userId, $from, $to)->toArray(),
            'pillars' => $this->pillars->__invoke($userId, $from, $to)->toArray(),
        ], 200);
    }

    /**
     * Four-pillar realization (FR-12, TASK-133): completed task minutes per
     * pillar vs the mapped program weekly targets.
     */
    public function pillars(Request $request): JsonResponse
    {
        $parsed = $this->parseRange($request);
        if ($parsed instanceof JsonResponse) {
            return $parsed;
        }
        [$userId, $from, $to] = $parsed;

        return response()->json($this->pillars->__invoke($userId, $from, $to)->toArray(), 200);
    }

    /**
     * Annual activity heatmap (FR-31, TASK-134): daily intensity from
     * completion/recharge/productive/progress, optionally filtered to a pillar.
     */
    public function heatmap(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'pillar' => ['nullable', 'string', 'in:karier,kesehatan,bahasa,branding,uncategorized'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $userId = $request->user()->id;

        $to = self::inclusiveTo(isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])
            : CarbonImmutable::now());
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])
            : $to->subDays(364); // default: trailing year

        if ($from->gt($to)) {
            return response()->json(['error' => 'from cannot be after to.'], 422);
        }

        $pillar = isset($validated['pillar']) ? new Pillar($validated['pillar']) : null;

        return response()->json(
            $this->heatmap->__invoke($userId, $from, $to, $pillar)->toArray(),
            200,
        );
    }

    /**
     * @return array{0: int, 1: CarbonImmutable, 2: CarbonImmutable}|JsonResponse
     */
    private function parseRange(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $to = self::inclusiveTo(isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])
            : CarbonImmutable::now());
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])
            : $to->startOfWeek();

        if ($from->gt($to)) {
            return response()->json(['error' => 'from cannot be after to.'], 422);
        }

        return [$request->user()->id, $from, $to];
    }

    /**
     * A date-only `to` (YYYY-MM-DD) means "through the end of that day";
     * otherwise same-day events would fall outside the period (UTC rollover).
     */
    private static function inclusiveTo(CarbonImmutable $to): CarbonImmutable
    {
        return $to->format('H:i:s') === '00:00:00' ? $to->endOfDay() : $to;
    }
}
