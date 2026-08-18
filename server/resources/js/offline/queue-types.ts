/**
 * General offline mutation queue (TASK-052, SRS §9.3, offline-sync.md).
 *
 * The general queue supports arbitrary entity types and operations (tasks,
 * notes, quick capture, canvas, etc.) using the SRS §9.3 mutation envelope.
 * IndexedDB is the cache/queue, never canonical — PostgreSQL remains
 * authoritative (offline-sync.md §Principle).
 */

/** Envelope fields per SRS §9.3 and offline-sync.md §Mutation envelope. */
export interface MutationEnvelope {
    operationId: string;
    entityType: string;
    entityId: number;
    operationType: string;
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

/** Result of attempting to apply one mutation to the server. */
export type ApplyOutcome = 'applied' | 'conflict' | 'retryable' | 'permanent';

/** Contract for applying a single offline operation to the server. */
export interface OfflineOperationApplier {
    apply(envelope: MutationEnvelope): Promise<ApplyOutcome>;
}

/**
 * Persistent store for outbound mutations (entity-agnostic).
 * The IndexedDB implementation is injected at the app boundary; an in-memory
 * store is used in tests.
 */
export interface OfflineMutationStore {
    enqueue(envelope: MutationEnvelope): Promise<void>;
    /** All non-terminal (not applied/permanent-failed) mutations, FIFO by timestamp. */
    listPending(): Promise<MutationEnvelope[]>;
    markSyncing(operationId: string): Promise<void>;
    markApplied(operationId: string): Promise<void>;
    markFailed(
        operationId: string,
        status: 'failed_retryable' | 'failed_permanent',
        error: string,
    ): Promise<void>;
}

/** Factory producing a fresh operation id (uuid). Injectable for tests. */
export type OperationIdFactory = () => string;
