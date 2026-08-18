/**
 * Canvas offline queue + sync orchestration (FR-57, SRS §9, offline-sync.md).
 *
 * Responsibilities:
 * - enqueue canvas mutations into the persistent store (FIFO);
 * - persist a local canvas snapshot so an offline edit survives tab close;
 * - track queue status and surface sync state;
 * - when online, sync queued mutations in order, applying conservative
 *   versioning (SRS §9.4): a stale canvas write is a conflict, never a silent
 *   last-write-wins overwrite.
 */
import type {
    MutationEnvelope,
    MutationStore,
    OfflineOperationApplier,
} from './offline';
import type { CanvasScene } from './types';

/** Visible queue state (offline-sync.md §Sync state machine). */
export type OfflineState =
    | 'idle' // nothing queued
    | 'queued' // mutations pending
    | 'syncing' // actively syncing
    | 'conflict' // a queued mutation hit a version conflict (needs reconcile)
    | 'failed_retryable'
    | 'failed_permanent';

export interface OfflineStateChange {
    state: OfflineState;
    queuedCount: number;
}

export interface OfflineQueueOptions {
    /** Optional factory producing a fresh operation id (uuid). Injectable for tests. */
    operationIdFactory?: () => string;
}

/**
 * Orchestrates the offline canvas mutation queue.
 *
 * `store` is injectable (IndexedDB in production, in-memory in tests).
 * `applier` executes one queued operation against the server and returns an
 * outcome; the queue maps that outcome to a durable status.
 */
export class CanvasOfflineQueue {
    private readonly store: MutationStore;
    private readonly applier: OfflineOperationApplier;
    private readonly operationIdFactory: () => string;
    private state: OfflineState = 'idle';
    private queuedCount = 0;
    private readonly listeners = new Set<(change: OfflineStateChange) => void>();
    private syncing = false;

    constructor(store: MutationStore, applier: OfflineOperationApplier, options: OfflineQueueOptions = {}) {
        this.store = store;
        this.applier = applier;
        this.operationIdFactory = options.operationIdFactory ?? defaultOperationId;
    }

    /** Current queue state. */
    getState(): OfflineState {
        return this.state;
    }

    getQueuedCount(): number {
        return this.queuedCount;
    }

    /** Subscribe to state changes. Returns unsubscribe. */
    subscribe(listener: (change: OfflineStateChange) => void): () => void {
        this.listeners.add(listener);
        return () => {
            this.listeners.delete(listener);
        };
    }

    /**
     * Queue a canvas mutation. Persists a local snapshot and appends the
     * envelope BEFORE reporting success so an offline edit survives tab close
     * (FR-57 acceptance).
     */
    async enqueue(canvasId: number, baseVersion: number, scene: CanvasScene): Promise<void> {
        const envelope: MutationEnvelope = {
            operationId: this.operationIdFactory(),
            entityType: 'canvas',
            entityId: canvasId,
            operationType: 'update',
            payload: { scene_json: scene },
            clientTimestamp: new Date().toISOString(),
            baseVersion,
            status: 'queued',
            attemptCount: 0,
        };
        await this.store.saveSnapshot(canvasId, scene, baseVersion);
        await this.store.enqueue(envelope);
        this.queuedCount += 1;
        this.setSyncState(this.state === 'conflict' ? 'conflict' : 'queued');
    }

    /** Sync all pending mutations FIFO. Returns the outcome of the batch. */
    async sync(): Promise<OfflineState> {
        if (this.syncing) {
            return this.state;
        }
        this.syncing = true;
        this.setSyncState('syncing');

        const pending = await this.store.listPending();
        let anyFailed = false;
        let conflicted = false;

        for (const envelope of pending) {
            await this.store.markSyncing(envelope.operationId);
            const outcome = await this.applier.apply(envelope);
            switch (outcome) {
                case 'applied':
                    await this.store.markApplied(envelope.operationId);
                    this.queuedCount = Math.max(0, this.queuedCount - 1);
                    break;
                case 'conflict':
                    // Conservative rule (SRS §9.4): keep local data, surface
                    // conflict, do not silently overwrite.
                    conflicted = true;
                    await this.store.markFailed(
                        envelope.operationId,
                        'failed_permanent',
                        'canvas version conflict',
                    );
                    this.queuedCount = Math.max(0, this.queuedCount - 1);
                    break;
                case 'retryable':
                    anyFailed = true;
                    await this.store.markFailed(
                        envelope.operationId,
                        'failed_retryable',
                        'transient error',
                    );
                    break;
                case 'permanent':
                    anyFailed = true;
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
        this.refreshState(conflicted, anyFailed);
        return this.state;
    }

    /** Discard a conflict after the caller has reconciled. */
    async clear(operationId: string): Promise<void> {
        await this.store.markApplied(operationId);
    }

    private refreshState(conflicted: boolean, anyFailed: boolean): void {
        if (conflicted) {
            this.setSyncState('conflict');
            return;
        }
        if (anyFailed) {
            this.setSyncState('failed_retryable');
            return;
        }
        if (this.queuedCount > 0) {
            this.setSyncState('queued');
            return;
        }
        this.setSyncState('idle');
    }

    private setSyncState(state: OfflineState): void {
        this.state = state;
        const payload: OfflineStateChange = { state, queuedCount: this.queuedCount };
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
