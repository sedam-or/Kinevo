/**
 * General offline mutation queue (TASK-052, FR-44, SRS §9.3, offline-sync.md).
 *
 * Responsibilities (offline-sync.md §Queue semantics):
 * - append a mutation and persist it BEFORE reporting local success;
 * - sync queued mutations FIFO where possible;
 * - collapse safe repeated mutations to the same entity where allowed
 *   (last-write-wins for low-risk operations, SRS §9.4);
 * - retry transient failures and surface permanent failures;
 * - never silently discard local data.
 *
 * IndexedDB is a cache/queue, never canonical (offline-sync.md §Principle).
 * The store and applier are injectable so the queue is testable without a
 * real IndexedDB.
 */
import type {
    MutationEnvelope,
    OfflineMutationStore,
    OfflineOperationApplier,
    OperationIdFactory,
} from './queue-types';

/** Visible queue state (offline-sync.md §Sync state machine). */
export type MutationQueueState =
    | 'idle'
    | 'queued'
    | 'syncing'
    | 'conflict'
    | 'failed_retryable'
    | 'failed_permanent';

export interface MutationQueueStateChange {
    state: MutationQueueState;
    queuedCount: number;
}

export interface MutationQueueOptions {
    operationIdFactory?: OperationIdFactory;
    /**
     * When true (default), consecutive queued mutations to the same entity
     * (same entityType+entityId) that carry NO baseVersion (i.e. not versioned
     * rich content) are collapsed to the latest payload — last-write-wins for
     * low-risk operations (SRS §9.4). Versioned operations are never collapsed.
     */
    collapseLastWriteWins?: boolean;
}

/**
 * Entity-agnostic offline mutation queue.
 *
 * `store` is injectable (IndexedDB in production, in-memory in tests).
 * `applier` executes one queued operation against the server and returns an
 * outcome; the queue maps that outcome to a durable status.
 */
export class MutationQueue {
    private readonly store: OfflineMutationStore;
    private readonly applier: OfflineOperationApplier;
    private readonly operationIdFactory: OperationIdFactory;
    private readonly collapseLastWriteWins: boolean;
    private state: MutationQueueState = 'idle';
    private queuedCount = 0;
    private readonly listeners = new Set<(change: MutationQueueStateChange) => void>();
    private syncing = false;

    constructor(
        store: OfflineMutationStore,
        applier: OfflineOperationApplier,
        options: MutationQueueOptions = {},
    ) {
        this.store = store;
        this.applier = applier;
        this.operationIdFactory = options.operationIdFactory ?? defaultOperationId;
        this.collapseLastWriteWins = options.collapseLastWriteWins ?? true;
    }

    getState(): MutationQueueState {
        return this.state;
    }

    getQueuedCount(): number {
        return this.queuedCount;
    }

    subscribe(listener: (change: MutationQueueStateChange) => void): () => void {
        this.listeners.add(listener);
        return () => {
            this.listeners.delete(listener);
        };
    }

    /**
     * Enqueue a mutation. Persists the envelope BEFORE resolving so an offline
     * mutation survives tab close (FR-44).
     */
    async enqueue(
        entityType: string,
        entityId: number,
        operationType: string,
        payload: Record<string, unknown>,
        baseVersion?: number,
    ): Promise<void> {
        const envelope: MutationEnvelope = {
            operationId: this.operationIdFactory(),
            entityType,
            entityId,
            operationType,
            payload,
            clientTimestamp: new Date().toISOString(),
            baseVersion,
            status: 'queued',
            attemptCount: 0,
        };
        await this.store.enqueue(envelope);
        if (this.collapseLastWriteWins && baseVersion === undefined) {
            await this.collapseDuplicates(envelope);
        }
        this.queuedCount += 1;
        this.setSyncState(this.state === 'conflict' ? 'conflict' : 'queued');
    }

    /** Sync all pending mutations FIFO. Returns the resulting state. */
    async sync(): Promise<MutationQueueState> {
        if (this.syncing) {
            return this.state;
        }
        this.syncing = true;
        this.setSyncState('syncing');

        const pending = await this.store.listPending();
        let conflicted = false;
        let failedRetryable = false;
        let failedPermanent = false;

        for (const envelope of pending) {
            await this.store.markSyncing(envelope.operationId);
            const outcome = await this.applier.apply(envelope);
            switch (outcome) {
                case 'applied':
                    await this.store.markApplied(envelope.operationId);
                    this.queuedCount = Math.max(0, this.queuedCount - 1);
                    break;
                case 'conflict':
                    // Conservative rule (SRS §9.4): keep local data, surface a
                    // conflict, do not silently overwrite or discard.
                    conflicted = true;
                    await this.store.markFailed(
                        envelope.operationId,
                        'failed_permanent',
                        'entity version conflict',
                    );
                    this.queuedCount = Math.max(0, this.queuedCount - 1);
                    break;
                case 'retryable':
                    failedRetryable = true;
                    await this.store.markFailed(
                        envelope.operationId,
                        'failed_retryable',
                        'transient error',
                    );
                    break;
                case 'permanent':
                    failedPermanent = true;
                    await this.store.markFailed(
                        envelope.operationId,
                        'failed_permanent',
                        'permanent error',
                    );
                    this.queuedCount = Math.max(0, this.queuedCount - 1);
                    break;
            }
        }

        this.syncing = false;
        this.refreshState(conflicted, failedRetryable, failedPermanent);
        return this.state;
    }

    /** Discard a conflict after the caller has reconciled. */
    async resolve(operationId: string): Promise<void> {
        await this.store.markApplied(operationId);
    }

    private async collapseDuplicates(newest: MutationEnvelope): Promise<void> {
        // Re-apply collapse semantics: remove earlier non-versioned queued
        // mutations to the same entity (last-write-wins), leaving the newest.
        const pending = await this.store.listPending();
        const toRemove = pending.filter(
            (m) =>
                m.operationId !== newest.operationId &&
                m.entityType === newest.entityType &&
                m.entityId === newest.entityId &&
                m.baseVersion === undefined,
        );
        for (const dup of toRemove) {
            await this.store.markApplied(dup.operationId);
            this.queuedCount = Math.max(0, this.queuedCount - 1);
        }
    }

    private refreshState(conflicted: boolean, failedRetryable: boolean, failedPermanent: boolean): void {
        if (conflicted) {
            this.setSyncState('conflict');
            return;
        }
        if (failedPermanent) {
            this.setSyncState('failed_permanent');
            return;
        }
        if (failedRetryable) {
            this.setSyncState('failed_retryable');
            return;
        }
        if (this.queuedCount > 0) {
            this.setSyncState('queued');
            return;
        }
        this.setSyncState('idle');
    }

    private setSyncState(state: MutationQueueState): void {
        this.state = state;
        const payload: MutationQueueStateChange = { state, queuedCount: this.queuedCount };
        for (const listener of this.listeners) {
            listener(payload);
        }
    }
}

function defaultOperationId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return `op-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}
