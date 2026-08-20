/**
 * Visible synchronization status (TASK-115, offline-sync.md §Sync state
 * machine).
 *
 * Bridges the entity-agnostic MutationQueue into the visible sync states the
 * UI presents (online/offline/queued/syncing/saved/conflict/retrying/failed).
 * The bridge is framework-agnostic: the queue is injectable and state changes
 * are delivered to an `onChange` sink (the shell store in the app, a spy in
 * tests). It never touches IndexedDB or HTTP directly.
 *
 * Mapping (offline-sync.md §Sync state machine):
 * - queue idle   -> online (or offline when the network is down)
 * - queued       -> queued
 * - syncing      -> syncing
 * - conflict     -> conflict
 * - failed_retryable    -> retrying
 * - failed_permanent    -> failed
 * - a completed sync that emptied the queue -> saved
 */
import type { MutationQueue, MutationQueueState, MutationQueueStateChange } from './queue';

/** The visible synchronization states the UI can present (TASK-115). */
export type VisibleSyncState =
    | 'online'
    | 'offline'
    | 'queued'
    | 'syncing'
    | 'saved'
    | 'conflict'
    | 'retrying'
    | 'failed';

/** All visible sync states, in a stable order. */
export const VISIBLE_SYNC_STATES: readonly VisibleSyncState[] = [
    'online',
    'offline',
    'queued',
    'syncing',
    'saved',
    'conflict',
    'retrying',
    'failed',
];

/** A stable, explainable snapshot of the synchronization status. */
export interface SyncStatus {
    state: VisibleSyncState;
    queuedCount: number;
    /** Human-readable explanation of what the state means for the user's data. */
    explanation: string;
    /** True when the user can retry synchronization right now. */
    retryable: boolean;
    /** Last failure message, when the state is retrying/failed. */
    error?: string;
}

export interface SyncStatusSink {
    (status: SyncStatus): void;
}

/** Contract for a connectivity provider (wraps navigator.onLine in the app). */
export interface OnlineStatusProvider {
    (): boolean;
}

/**
 * Map a raw queue state to the visible sync state, incorporating the network
 * status. Exported for unit testing the mapping directly.
 */
export function mapQueueStateToSyncState(
    queueState: MutationQueueState,
    online: boolean,
    lastSyncSucceeded: boolean,
): VisibleSyncState {
    if (queueState === 'queued') {
        return 'queued';
    }
    if (queueState === 'syncing') {
        return 'syncing';
    }
    if (queueState === 'conflict') {
        return 'conflict';
    }
    if (queueState === 'failed_retryable') {
        return 'retrying';
    }
    if (queueState === 'failed_permanent') {
        return 'failed';
    }
    if (!online) {
        return 'offline';
    }
    return lastSyncSucceeded ? 'saved' : 'online';
}

/** Human-readable meaning of each visible sync state (design.md §Sync). */
export const SYNC_STATE_EXPLANATIONS: Record<VisibleSyncState, string> = {
    online: 'Online — changes are saved to the server as you make them.',
    offline: 'Offline — changes are saved on this device and will sync when you reconnect.',
    queued: 'Queued — changes are saved locally and waiting to sync.',
    syncing: 'Syncing — sending your local changes to the server now.',
    saved: 'Saved — your latest changes were sent to the server.',
    conflict: 'Conflict — a local change conflicts with the server. Review it to keep your data.',
    retrying: 'Retrying — a sync attempt failed temporarily; trying again.',
    failed: 'Failed — a change could not sync. Your local copy is preserved; try again.',
};

/** States that carry an error message and offer a manual retry. */
const RETRYABLE_STATES: readonly VisibleSyncState[] = ['retrying', 'failed'];

function canRetry(state: VisibleSyncState): boolean {
    return RETRYABLE_STATES.includes(state);
}

/**
 * Bridge a MutationQueue into a SyncStatus sink. Creates no timers and makes
 * no network calls of its own; `sync()` and `retry()` delegate to the queue.
 */
export class SyncStatusController {
    private readonly queue: MutationQueue;
    private readonly isOnline: OnlineStatusProvider;
    private readonly sink: SyncStatusSink;
    private readonly unsubscribeFromQueue: () => void;
    private lastSyncSucceeded = false;
    private lastError: string | undefined;
    private queuedCount = 0;

    constructor(
        queue: MutationQueue,
        sink: SyncStatusSink,
        isOnline: OnlineStatusProvider = () => true,
    ) {
        this.queue = queue;
        this.sink = sink;
        this.isOnline = isOnline;
        this.queuedCount = queue.getQueuedCount();
        this.unsubscribeFromQueue = queue.subscribe((change) => this.onQueueChange(change));
        this.publish();
    }

    /** Current visible sync status. */
    getStatus(): SyncStatus {
        return this.buildStatus(this.queue.getState());
    }

    /** Trigger a sync pass; returns the resulting queue state. */
    async sync(): Promise<MutationQueueState> {
        const result = await this.queue.sync();
        // A successful pass that drained the queue is a "Saved" outcome.
        this.lastSyncSucceeded = result === 'idle' && this.queue.getQueuedCount() === 0;
        this.publish();
        return result;
    }

    /** Manual retry (UI action); delegates to the queue's sync pass. */
    async retry(): Promise<void> {
        const result = await this.queue.sync();
        this.lastSyncSucceeded = result === 'idle' && this.queue.getQueuedCount() === 0;
        this.publish();
    }

    /** Refresh the visible status (e.g. when connectivity changes). */
    refresh(): void {
        this.publish();
    }

    /** Release the queue subscription. */
    dispose(): void {
        this.unsubscribeFromQueue();
    }

    private onQueueChange(change: MutationQueueStateChange): void {
        this.queuedCount = change.queuedCount;
        // New work invalidates a previous "Saved" outcome until it drains.
        if (change.state === 'queued' || change.state === 'syncing') {
            this.lastSyncSucceeded = false;
        }
        if (change.state === 'failed_retryable' || change.state === 'failed_permanent') {
            this.lastError = 'Sync failed. Your local changes are preserved.';
        }
        this.publish();
    }

    private buildStatus(queueState: MutationQueueState): SyncStatus {
        const online = this.isOnline();
        const state = mapQueueStateToSyncState(queueState, online, this.lastSyncSucceeded);
        const status: SyncStatus = {
            state,
            queuedCount: this.queuedCount,
            explanation: SYNC_STATE_EXPLANATIONS[state],
            retryable: canRetry(state),
        };
        if (this.lastError !== undefined) {
            status.error = this.lastError;
        }
        return status;
    }

    private publish(): void {
        this.sink(this.buildStatus(this.queue.getState()));
    }
}