<?php

namespace App\Http\Controllers\Api;

use App\Application\Scheduling\ScheduleQueryService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Today view (FR-01): the canonical 24-hour timeline for a date, including
 * scheduled events, empty (fillable) slots, capacity indicators, and (once
 * TASK-095 lands) hard landscape. Read-only.
 */
final class TodayController extends Controller
{
    public function __construct(
        private readonly ScheduleQueryService $scheduleQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $date = CarbonImmutable::parse($validator->validated()['date']);

        try {
            $view = $this->scheduleQuery->dayView($request->user()->id, $date);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($view);
    }
}
