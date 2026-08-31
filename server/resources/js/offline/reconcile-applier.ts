/**
 * ADR-017 §2.14 — reconcile applier for the general offline mutation queue.
 *
 * Posts each queued envelope to `POST /sync/reconcile` (the server-authoritative
 * operation ledger) and maps the server outcome to the queue's vocabulary:
 * - applied            -> the mutation is canonically persisted (or replayed);
 * - conflict           -> version/state conflict — keep local intent, surface;
 * - rejected/expired   -> permanent — surface, never endless-retry;
 * - network/429/5xx    -> retryable.
 *
 * Uses the existing `apiClient` — no new transport dependency.
 */
import { apiClient, ApiErrorClass, isNetworkFailure } from '../api/client';
import type { MutationEnvelope, OfflineOperationApplier, ApplyOutcome } from './queue-types';

interface ReconcileOutcome {
    status: 'applied' | 'conflict' | 'rejected';
    code?: string;
    replay?: boolean;
    error?: string;
}

/**
 * Map a thrown error to the queue's outcome vocabulary. Exported for tests.
 */
export function outcomeForError(error: unknown): ApplyOutcome {
    if (error instanceof ApiErrorClass) {
        if (error.status === 409) {
            return 'conflict';
        }
        if (error.status === 0 || error.status === 429 || error.status >= 500) {
            return 'retryable';
        }
        return 'permanent';
    }
    if (isNetworkFailure(error)) {
        return 'retryable';
    }
    return 'permanent';
}

/**
 * Apply one queued envelope through the reconciliation endpoint.
 *
 * A `conflict` outcome is remembered on the envelope via the returned marker so
 * the UI can surface "Some changes need review" (ADR-017 §2.18). The queue
 * stores `lastError` as a compact JSON string.
 */
export class ReconcileMutationApplier implements OfflineOperationApplier {
    async apply(envelope: MutationEnvelope): Promise<ApplyOutcome> {
        try {
            const response = await apiClient.request<{ outcomes: ReconcileOutcome[] }>('/sync/reconcile', {
                method: 'POST',
                body: JSON.stringify({ operations: [this.toEnvelopeWire(envelope)] }),
                noRetry: true,
            });

            const outcome = response.outcomes?.[0];
            if (outcome === undefined) {
                return 'permanent';
            }

            if (outcome.status === 'applied') {
                return 'applied';
            }
            if (outcome.status === 'conflict') {
                // Preserve the conflict detail for the review surface.
                envelope.lastError = JSON.stringify({
                    code: outcome.code ?? 'CONFLICT',
                    error: outcome.error ?? 'entity version conflict',
                });
                return 'conflict';
            }

            envelope.lastError = outcome.error ?? `rejected: ${outcome.code ?? 'REJECTED'}`;
            return 'permanent';
        } catch (error) {
            return outcomeForError(error);
        }
    }

    private toEnvelopeWire(envelope: MutationEnvelope): Record<string, unknown> {
        return {
            protocol_version: 1,
            operation_id: envelope.operationId,
            operation_type: envelope.operationType,
            entity_type: envelope.entityType,
            entity_id: envelope.entityId,
            payload: envelope.payload,
            base_version: envelope.baseVersion ?? null,
            workspace_id: (envelope.payload as Record<string, unknown>).workspace_id ?? null,
            client_created_at: envelope.clientTimestamp,
        };
    }
}

/** @deprecated use ReconcileMutationApplier — retained for tests only. */
export class HttpMutationApplier extends ReconcileMutationApplier {}