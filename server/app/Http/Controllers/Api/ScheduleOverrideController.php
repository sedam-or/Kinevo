<?php

namespace App\Http\Controllers\Api;

use App\Application\Scheduling\CreateScheduleOverrideUseCase;
use App\Application\Scheduling\DeleteScheduleOverrideUseCase;
use App\Application\Scheduling\GetScheduleOverrideUseCase;
use App\Application\Scheduling\ListScheduleOverridesUseCase;
use App\Application\Scheduling\UpdateScheduleOverrideUseCase;
use App\Domain\Scheduling\ScheduleOverrideConflict;
use App\Domain\Scheduling\ValueObjects\ScheduleOverrideType;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Schedule Override CRUD (FR-25; SRS §7.1 `schedule_overrides`). Applies a
 * permanent shift or one-time exception to a recurring Hard Landscape series.
 * Ownership-scoped; overlapping overrides for the same source are rejected.
 */
final class ScheduleOverrideController extends Controller
{
    public function __construct(
        private readonly ListScheduleOverridesUseCase $listUseCase,
        private readonly GetScheduleOverrideUseCase $getUseCase,
        private readonly CreateScheduleOverrideUseCase $createUseCase,
        private readonly UpdateScheduleOverrideUseCase $updateUseCase,
        private readonly DeleteScheduleOverrideUseCase $deleteUseCase,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $overrides = array_map(
            fn ($override) => $override->toArray(),
            $this->listUseCase->__invoke($request->user()->id),
        );

        return response()->json(['overrides' => $overrides]);
    }

    public function show(Request $request, int $overrideId): JsonResponse
    {
        try {
            $override = $this->getUseCase->__invoke($request->user()->id, $overrideId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['override' => $override->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hard_landscape_event_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:permanent,one_time'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['required', 'date', 'after_or_equal:effective_from'],
            'override_start_at' => ['required', 'date'],
            'override_end_at' => ['required', 'date', 'after:override_start_at'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $override = $this->createUseCase->__invoke(
                $request->user()->id,
                (int) $data['hard_landscape_event_id'],
                new ScheduleOverrideType($data['type']),
                CarbonImmutable::parse($data['effective_from']),
                CarbonImmutable::parse($data['effective_to']),
                CarbonImmutable::parse($data['override_start_at']),
                CarbonImmutable::parse($data['override_end_at']),
                $data['reason'] ?? null,
            );
        } catch (ScheduleOverrideConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Hard Landscape event not found or does not belong to user.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['override' => $override->toArray()], 201);
    }

    public function update(Request $request, int $overrideId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hard_landscape_event_id' => ['sometimes', 'integer'],
            'type' => ['sometimes', 'string', 'in:permanent,one_time'],
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['sometimes', 'date'],
            'override_start_at' => ['sometimes', 'date'],
            'override_end_at' => ['sometimes', 'date'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $override = $this->updateUseCase->__invoke(
                $request->user()->id,
                $overrideId,
                isset($data['hard_landscape_event_id']) ? (int) $data['hard_landscape_event_id'] : null,
                isset($data['type']) ? new ScheduleOverrideType($data['type']) : null,
                isset($data['effective_from']) ? CarbonImmutable::parse($data['effective_from']) : null,
                isset($data['effective_to']) ? CarbonImmutable::parse($data['effective_to']) : null,
                isset($data['override_start_at']) ? CarbonImmutable::parse($data['override_start_at']) : null,
                isset($data['override_end_at']) ? CarbonImmutable::parse($data['override_end_at']) : null,
                $data['reason'] ?? null,
            );
        } catch (ScheduleOverrideConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Schedule override not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['override' => $override->toArray()]);
    }

    public function destroy(Request $request, int $overrideId): JsonResponse
    {
        try {
            $this->deleteUseCase->__invoke($request->user()->id, $overrideId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['deleted' => true]);
    }
}
