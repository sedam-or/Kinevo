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
 * Schedule query (FR-01 navigation): list assignments for a single date
 * (`?date=`) or an arbitrary range (`?from=&to=`). Read-only.
 */
final class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleQueryService $scheduleQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['sometimes', 'date'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $userId = $request->user()->id;

        try {
            if (isset($data['from']) && isset($data['to'])) {
                $view = $this->scheduleQuery->rangeView(
                    $userId,
                    CarbonImmutable::parse($data['from']),
                    CarbonImmutable::parse($data['to']),
                );
            } elseif (isset($data['date'])) {
                $view = $this->scheduleQuery->dayView(
                    $userId,
                    CarbonImmutable::parse($data['date']),
                );
            } else {
                return response()->json(['errors' => ['You must provide date or from/to.']], 422);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($view);
    }
}
