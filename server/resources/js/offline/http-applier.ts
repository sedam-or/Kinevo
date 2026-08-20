/**
 * HTTP applier for the general offline mutation queue (TASK-115, FR-44,
 * offline-sync.md §Sync flow).
 *
 * Maps an offline MutationEnvelope to the matching Kinevo API mutation and
 * translates the outcome/errors into the queue's ApplyOutcome vocabulary:
 * - applied   -> the server accepted the mutation;
 * - conflict  -> 409 version/state conflict (SRS §9.4 conservative rule);
 * - retryable -> offline/network/transient 5xx or 429 (safe to retry);
 * - permanent -> other 4xx (payload/authorization errors are not retried).
 *
 * Uses the existing `apiClient` (api/client.ts) — no new transport dependency.
 * Unsupported entity/operation combinations are a permanent failure: the local
 * copy is preserved and surfaced to the user rather than silently dropped.
 */
import { apiClient, ApiErrorClass, isNetworkFailure } from '../api/client';
import type { MutationEnvelope, OfflineOperationApplier, ApplyOutcome } from './queue-types';

/** Dispatch table: (entityType, operationType) -> HTTP method + path template. */
interface Route {
    method: 'POST' | 'PUT' | 'PATCH';
    path: (envelope: MutationEnvelope) => string;
}

const ROUTES: Record<string, Route> = {
    'task:create': { method: 'POST', path: () => '/tasks' },
    'task:update': { method: 'PUT', path: (e) => `/tasks/${e.entityId}` },
    'task:status': { method: 'POST', path: (e) => `/tasks/${e.entityId}/status` },
    'note:create': { method: 'POST', path: () => '/notes' },
    'note:update': { method: 'PATCH', path: (e) => `/notes/${e.entityId}` },
    'quick_capture:create': { method: 'POST', path: () => '/quick-capture' },
    'canvas:update': { method: 'PUT', path: (e) => `/canvases/${e.entityId}` },
};

function routeFor(envelope: MutationEnvelope): Route | undefined {
    return ROUTES[`${envelope.entityType}:${envelope.operationType}`];
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

export class HttpMutationApplier implements OfflineOperationApplier {
    async apply(envelope: MutationEnvelope): Promise<ApplyOutcome> {
        const route = routeFor(envelope);
        if (route === undefined) {
            // Unsupported operation: never silently discard the local copy.
            return 'permanent';
        }

        try {
            await apiClient.request<unknown>(route.path(envelope), {
                method: route.method,
                body: JSON.stringify(envelope.payload),
                noRetry: true,
            });
            return 'applied';
        } catch (error) {
            return outcomeForError(error);
        }
    }
}