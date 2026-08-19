<?php

namespace App\Http\Controllers\Api;

use App\Application\Scheduling\CreateHardLandscapeUseCase;
use App\Application\Scheduling\DeleteHardLandscapeUseCase;
use App\Application\Scheduling\GetHardLandscapeUseCase;
use App\Application\Scheduling\ListHardLandscapeUseCase;
use App\Application\Scheduling\UpdateHardLandscapeUseCase;
use App\Domain\Scheduling\HardLandscapeConflict;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Hard Landscape CRUD (SRS §7.1, `hard_landscape_events`; FR-27/FR-28).
 * Ownership-scoped; overlapping blocks are rejected with 409.
 */
final class HardLandscapeController extends Controller
{
    public function __construct(
        private readonly ListHardLandscapeUseCase $listUseCase,
        private readonly GetHardLandscapeUseCase $getUseCase,
        private readonly CreateHardLandscapeUseCase $createUseCase,
        private readonly UpdateHardLandscapeUseCase $updateUseCase,
        private readonly DeleteHardLandscapeUseCase $deleteUseCase,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $events = array_map(
            fn ($event) => $event->toArray(),
            $this->listUseCase->__invoke($request->user()->id),
        );

        return response()->json(['hard_landscape' => $events]);
    }

    public function show(Request $request, int $eventId): JsonResponse
    {
        try {
            $event = $this->getUseCase->__invoke($request->user()->id, $eventId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['hard_landscape' => $event->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'type' => ['required', 'string', 'in:permanent,recurring,one_time'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'recurrence' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $event = $this->createUseCase->__invoke(
                $request->user()->id,
                $data['title'],
                new HardLandscapeType($data['type']),
                CarbonImmutable::parse($data['start_at']),
                CarbonImmutable::parse($data['end_at']),
                $data['recurrence'] ?? null,
            );
        } catch (HardLandscapeConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['hard_landscape' => $event->toArray()], 201);
    }

    public function update(Request $request, int $eventId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'min:1', 'max:200'],
            'type' => ['sometimes', 'string', 'in:permanent,recurring,one_time'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date'],
            'recurrence' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $event = $this->updateUseCase->__invoke(
                $request->user()->id,
                $eventId,
                $data['title'] ?? null,
                isset($data['type']) ? new HardLandscapeType($data['type']) : null,
                isset($data['start_at']) ? CarbonImmutable::parse($data['start_at']) : null,
                isset($data['end_at']) ? CarbonImmutable::parse($data['end_at']) : null,
                $data['recurrence'] ?? null,
            );
        } catch (HardLandscapeConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Hard Landscape event not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['hard_landscape' => $event->toArray()]);
    }

    public function destroy(Request $request, int $eventId): JsonResponse
    {
        try {
            $this->deleteUseCase->__invoke($request->user()->id, $eventId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['deleted' => true]);
    }
}
