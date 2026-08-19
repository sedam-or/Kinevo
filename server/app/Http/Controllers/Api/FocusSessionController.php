<?php

namespace App\Http\Controllers\Api;

use App\Application\Focus\ListFocusSessionsUseCase;
use App\Application\Focus\RecommendFocusBlockUseCase;
use App\Application\Focus\RecordFocusSessionUseCase;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class FocusSessionController extends Controller
{
    public function __construct(
        private readonly RecordFocusSessionUseCase $recordSession,
        private readonly ListFocusSessionsUseCase $listSessions,
        private readonly RecommendFocusBlockUseCase $recommendFocus,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'task_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'between:1,200'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $sessions = $this->listSessions->__invoke(
            $request->user()->id,
            $data['task_id'] ?? null,
            $data['limit'] ?? 50,
        );

        return response()->json([
            'sessions' => array_map(static fn ($session) => $session->toArray(), $sessions),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'task_id' => ['nullable', 'integer'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $session = $this->recordSession->__invoke(
                $request->user()->id,
                CarbonImmutable::parse($data['started_at']),
                CarbonImmutable::parse($data['ended_at']),
                $data['task_id'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Task not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['session' => $session->toArray()], 201);
    }

    public function recommendation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'task_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $recommendation = $this->recommendFocus->__invoke(
            $request->user()->id,
            $validator->validated()['task_id'] ?? null,
        );

        return response()->json([
            'recommended_minutes' => $recommendation->recommendedMinutes,
            'basis' => $recommendation->basis,
            'sample_count' => $recommendation->sampleCount,
            'reason' => $recommendation->reason,
        ]);
    }
}
