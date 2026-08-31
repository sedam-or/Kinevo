<?php

namespace App\Http\Controllers\Api;

use App\Application\OfflineSync\OfflineReconciliationService;
use App\Domain\OfflineSync\OperationEnvelope;
use App\Domain\OfflineSync\OperationOutcome;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * ADR-017 §2.7 — OFFLINE MUTATION RECONCILIATION (distinct from the schedule
 * "Sync Now" at POST /schedule/sync). Accepts a bounded batch of offline
 * operation envelopes, replays them through the canonical use cases with
 * per-operation idempotency, and returns one outcome per operation.
 */
final class SyncReconcileController extends Controller
{
    public function __construct(
        private readonly OfflineReconciliationService $reconciliation,
    ) {}

    public function reconcile(Request $request): JsonResponse
    {
        if ($request->hasHeader('Content-Length') && (int) $request->header('Content-Length') > config('offline.max_request_bytes')) {
            return response()->json(['error' => 'Reconcile request exceeds the size limit.'], 413);
        }

        $validator = Validator::make($request->all(), [
            'operations' => ['required', 'array', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $operations = $request->input('operations');
        if (! is_array($operations)) {
            return response()->json(['error' => 'operations must be an array.'], 422);
        }

        $maxBatch = config('offline.max_operations_per_batch');
        if (count($operations) > $maxBatch) {
            return response()->json([
                'error' => "A reconcile batch may contain at most {$maxBatch} operations.",
            ], 422);
        }

        $envelopes = [];
        foreach ($operations as $index => $raw) {
            if (! is_array($raw)) {
                return response()->json(['error' => "operations[{$index}] must be an object."], 422);
            }

            if (strlen(json_encode($raw)) > config('offline.max_payload_bytes_per_operation')) {
                return response()->json(['error' => "operations[{$index}] exceeds the per-operation payload limit."], 422);
            }

            try {
                $envelopes[] = OperationEnvelope::fromArray($raw);
            } catch (InvalidArgumentException $e) {
                return response()->json(['error' => "operations[{$index}]: {$e->getMessage()}"], 422);
            }
        }

        $userId = $request->user()->id;
        $outcomes = $this->reconciliation->reconcileBatch($userId, $envelopes);

        $needsReview = array_values(array_filter(
            $outcomes,
            static fn (OperationOutcome $outcome) => $outcome->status === OperationOutcome::CONFLICT,
        ));

        return response()->json([
            'outcomes' => array_map(
                static fn (OperationOutcome $outcome) => $outcome->toArray(),
                $outcomes,
            ),
            'needs_review' => array_map(
                static fn (OperationOutcome $outcome) => $outcome->operationId,
                $needsReview,
            ),
        ]);
    }
}
