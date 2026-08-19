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
 * Monthly calendar (FR-15): a per-day summary grid with event indicators.
 * Read-only.
 */
final class CalendarController extends Controller
{
    public function __construct(
        private readonly ScheduleQueryService $scheduleQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'month' => ['sometimes', 'date_format:Y-m'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $month = $validator->validated()['month'] ?? CarbonImmutable::today()->format('Y-m');
        $parts = explode('-', $month);

        try {
            $view = $this->scheduleQuery->monthView(
                $request->user()->id,
                (int) $parts[0],
                (int) $parts[1],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($view);
    }
}
