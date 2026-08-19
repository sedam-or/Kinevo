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
 * Week view (FR-11): a 7-day summary consistent with the Today view.
 * Read-only.
 */
final class WeekController extends Controller
{
    public function __construct(
        private readonly ScheduleQueryService $scheduleQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['sometimes', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $date = isset($validator->validated()['date'])
            ? CarbonImmutable::parse($validator->validated()['date'])
            : CarbonImmutable::today();

        try {
            $view = $this->scheduleQuery->weekView($request->user()->id, $date);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($view);
    }
}
