/**
 * Canvas offline mutation queue (TASK-044, FR-57, SRS §9.3, offline-sync.md).
 *
 * IndexedDB is the cache/queue, never the canonical source of truth —
 * PostgreSQL remains authoritative (offline-sync.md §Principle). This module
 * is framework-agnostic: the storage layer is injectable so the queue/sync
 * logic is testable without a real IndexedDB.
 */

/** Envelope fields per SRS §9.3 and offline-sync.md §Mutation envelope. */
export interface MutationEnvelope {
    operationId: string;
    entityType: 'canvas';
    entityId: number;
    operationType: 'update';
    payload: Record<string, unknown>;
    clientTimestamp: string;
    baseVersion?: number;
    status: MutationStatus;
    attemptCount: number;
    lastError?: string;
}

/** Sync state machine (offline-sync.md §Sync state machine). */
export type MutationStatus =
    | 'queued'
    | 'syncing'
    | 'applied'
    | 'failed_retryable'
    | 'failed_permanent';

/** Result of attempting to persist/apply one mutation. */
export type ApplyOutcome = 'applied' | 'conflict' | 'retryable' | 'permanent';

/** Contract for a single offline operation application. */
export interface OfflineOperationApplier {
    apply(envelope: MutationEnvelope): Promise<ApplyOutcome>;
}

/**
 * Persistent store for outbound mutations. The IndexedDB implementation is
 * injected at the app boundary; an in-memory store is used in tests.
 */
export interface MutationStore {
    enqueue(envelope: MutationEnvelope): Promise<void>;
    /** All non-terminal (not applied/permanent-failed) mutations, FIFO by timestamp. */
    listPending(): Promise<MutationEnvelope[]>;
    markSyncing(operationId: string): Promise<void>;
    markApplied(operationId: string): Promise<void>;
    markFailed(operationId: string, status: 'failed_retryable' | 'failed_permanent', error: string): Promise<void>;
    /** Persist a local canvas snapshot for offline editing (SRS §9.2). */
    saveSnapshot(canvasId: number, scene: unknown, baseVersion: number): Promise<void>;
    getSnapshot(canvasId: number): Promise<{ scene: unknown; baseVersion: number } | null>;
}
