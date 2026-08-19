<?php

namespace App\Http\Controllers\Api;

use App\Application\Progress\ListProgressEventsUseCase;
use App\Application\Progress\RecordProgressEventUseCase;
use App\Domain\Progress\ProgressEvent;
use App\Domain\Progress\ValueObjects\ProgressEventType;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class ProgressEventController extends Controller
{
    public function __construct(
        private readonly ListProgressEventsUseCase $listEvents,
        private readonly RecordProgressEventUseCase $recordEvent,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'event_type' => ['nullable', 'string', 'in:task_completed,milestone_advanced,milestone_completed,evidence_attached,experiment_recorded,goal_progress'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $events = $this->listEvents->__invoke(
            $request->user()->id,
            isset($data['from']) ? CarbonImmutable::parse($data['from']) : null,
            isset($data['to']) ? CarbonImmutable::parse($data['to']) : null,
            $data['event_type'] ?? null,
            $data['limit'] ?? 50,
        );

        return response()->json([
            'events' => array_map(static fn ($event) => $event->toArray(), $events),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_type' => ['required', 'string', 'in:evidence_attached,experiment_recorded,goal_progress'],
            'title' => ['required', 'string', 'max:200'],
            'entity_type' => ['nullable', 'string', 'max:20'],
            'entity_id' => ['nullable', 'integer'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $event = $this->recordEvent->__invoke(ProgressEvent::create(
                $request->user()->id,
                new ProgressEventType($data['event_type']),
                $data['entity_type'] ?? 'custom',
                $data['entity_id'] ?? 0,
                $data['title'],
                isset($data['occurred_at']) ? CarbonImmutable::parse($data['occurred_at']) : null,
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['event' => $event->toArray()], 201);
    }
}
