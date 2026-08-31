/**
 * ADR-017 §2.14 — offline-aware submission for allowlisted mutations.
 *
 * - offline → enqueue the operation (durably, before any local success);
 * - online → run the real online request with `X-Operation-Id`; if the request
 *   fails with a NETWORK error (the server may have committed but the response
 *   was lost), enqueue with the SAME operation_id so the drain replay is
 *   idempotent — never a double apply.
 *
 * Rejects (4xx) propagate to the caller as today; they are not queued.
 */
import type { MutationQueue } from './queue';

export interface OfflineMutationSpec {
    entityType: string;
    operationType: string;
    entityId: number | null;
    payload: Record<string, unknown>;
    baseVersion?: number;
    workspaceId?: number | null;
}

export interface OfflineMutationResult<T = unknown> {
    queued: boolean;
    operationId: string;
    /** The online request response, when the request actually ran. */
    value?: T;
}

function defaultOperationId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return `op-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function isNetworkFailure(error: unknown): boolean {
    const status = (error as { status?: number } | undefined)?.status;
    return status === 0 || status === undefined;
}

export async function submitOfflineAware<T>(
    queue: MutationQueue,
    spec: OfflineMutationSpec,
    online: (operationId: string) => Promise<T>,
    operationIdFactory: () => string = defaultOperationId,
): Promise<OfflineMutationResult<T>> {
    const offline = typeof navigator !== 'undefined' && navigator.onLine === false;

    if (offline) {
        await queue.enqueue(spec.entityType, spec.entityId, spec.operationType, spec.payload, spec.baseVersion);

        return { queued: true, operationId: '' };
    }

    const operationId = operationIdFactory();

    try {
        const value = await online(operationId);

        return { queued: false, operationId, value };
    } catch (error) {
        if (isNetworkFailure(error)) {
            await queue.enqueue(spec.entityType, spec.entityId, spec.operationType, spec.payload, spec.baseVersion);

            return { queued: true, operationId };
        }
        throw error;
    }
}