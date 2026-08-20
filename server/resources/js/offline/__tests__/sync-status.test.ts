import { describe, expect, it, vi } from 'vitest';
import {
    mapQueueStateToSyncState,
    SyncStatusController,
    SYNC_STATE_EXPLANATIONS,
    type VisibleSyncState,
} from '../sync-status';
import { MutationQueue } from '../queue';
import type {
    ApplyOutcome,
    MutationEnvelope,
    OfflineMutationStore,
    OfflineOperationApplier,
} from '../queue-types';

/** In-memory OfflineMutationStore for tests (happy-dom has no IndexedDB). */
class MemoryQueueStore implements OfflineMutationStore {
    mutations = new Map<string, MutationEnvelope>();
    applied: string[] = [];

    async enqueue(envelope: MutationEnvelope): Promise<void> {
        this.mutations.set(envelope.operationId, envelope);
    }

    async listPending(): Promise<MutationEnvelope[]> {
        return [...this.mutations.values()]
            .filter((m) => m.status === 'queued' || m.status === 'syncing' || m.status === 'failed_retryable')
            .sort((a, b) => new Date(a.clientTimestamp).getTime() - new Date(b.clientTimestamp).getTime());
    }

    async markSyncing(operationId: string): Promise<void> {
        const m = this.mutations.get(operationId);
        if (m) {
            this.mutations.set(operationId, { ...m, status: 'syncing' });
        }
    }

    async markApplied(operationId: string): Promise<void> {
        this.mutations.delete(operationId);
        this.applied.push(operationId);
    }

    async markFailed(
        operationId: string,
        status: 'failed_retryable' | 'failed_permanent',
        error: string,
    ): Promise<void> {
        const m = this.mutations.get(operationId);
        if (m) {
            this.mutations.set(operationId, { ...m, status, lastError: error });
        }
    }
}

function makeApplier(outcome: ApplyOutcome): OfflineOperationApplier {
    return { apply: vi.fn(async () => outcome) };
}

let idCounter = 0;
function opIdFactory(): string {
    idCounter += 1;
    return `op-${idCounter}`;
}

function makeQueue(outcome: ApplyOutcome = 'applied'): MutationQueue {
    return new MutationQueue(new MemoryQueueStore(), makeApplier(outcome), {
        operationIdFactory: opIdFactory,
    });
}

describe('mapQueueStateToSyncState', () => {
    it('maps each queue state to the visible sync state (TASK-115)', () => {
        expect(mapQueueStateToSyncState('queued', true, false)).toBe('queued');
        expect(mapQueueStateToSyncState('syncing', true, false)).toBe('syncing');
        expect(mapQueueStateToSyncState('conflict', true, false)).toBe('conflict');
        expect(mapQueueStateToSyncState('failed_retryable', true, false)).toBe('retrying');
        expect(mapQueueStateToSyncState('failed_permanent', true, false)).toBe('failed');
        expect(mapQueueStateToSyncState('idle', false, false)).toBe('offline');
        expect(mapQueueStateToSyncState('idle', true, false)).toBe('online');
        expect(mapQueueStateToSyncState('idle', true, true)).toBe('saved');
    });

    it('explains every visible state', () => {
        const states: VisibleSyncState[] = [
            'online',
            'offline',
            'queued',
            'syncing',
            'saved',
            'conflict',
            'retrying',
            'failed',
        ];
        for (const state of states) {
            expect(SYNC_STATE_EXPLANATIONS[state].length).toBeGreaterThan(0);
        }
    });
});

describe('SyncStatusController', () => {
    it('starts online with an empty queue when idle and connected', () => {
        const queue = makeQueue();
        const sink = vi.fn();
        const controller = new SyncStatusController(queue, sink, () => true);

        const status = controller.getStatus();
        expect(status.state).toBe('online');
        expect(status.queuedCount).toBe(0);
        expect(status.retryable).toBe(false);
        expect(sink).toHaveBeenCalled();
    });

    it('reports offline when disconnected even with an idle queue', () => {
        const queue = makeQueue();
        const sink = vi.fn();
        const controller = new SyncStatusController(queue, sink, () => false);
        expect(controller.getStatus().state).toBe('offline');
    });

    it('surfaces queued and syncing states as the queue progresses', async () => {
        const queue = makeQueue();
        const sink = vi.fn();
        const controller = new SyncStatusController(queue, sink, () => true);

        await queue.enqueue('task', 7, 'update', { title: 'Do it' });
        expect(controller.getStatus().state).toBe('queued');
        expect(controller.getStatus().queuedCount).toBe(1);

        await controller.sync();
        expect(controller.getStatus().state).toBe('saved');
        expect(controller.getStatus().queuedCount).toBe(0);
    });

    it('surfaces conflict without discarding the local mutation', async () => {
        const queue = makeQueue('conflict');
        const sink = vi.fn();
        const controller = new SyncStatusController(queue, sink, () => true);

        await queue.enqueue('task', 7, 'update', { title: 'Do it' });
        await controller.sync();

        expect(controller.getStatus().state).toBe('conflict');
        expect(controller.getStatus().retryable).toBe(false);
        expect(controller.getStatus().explanation).toContain('conflict');
    });

    it('surfaces retrying for retryable failures and failed for permanent ones', async () => {
        const retryable = makeQueue('retryable');
        const permanent = makeQueue('permanent');

        const retryController = new SyncStatusController(retryable, () => undefined, () => true);
        await retryable.enqueue('task', 7, 'update', { title: 'Do it' });
        await retryController.sync();
        expect(retryController.getStatus().state).toBe('retrying');
        expect(retryController.getStatus().retryable).toBe(true);
        expect(retryController.getStatus().error).toBeTruthy();

        const permanentController = new SyncStatusController(permanent, () => undefined, () => true);
        await permanent.enqueue('task', 8, 'update', { title: 'Do it' });
        await permanentController.sync();
        expect(permanentController.getStatus().state).toBe('failed');
        expect(permanentController.getStatus().retryable).toBe(true);
    });

    it('retry re-syncs and returns to saved when the failure clears', async () => {
        const store = new MemoryQueueStore();
        const applier = vi.fn<() => Promise<ApplyOutcome>>();
        const queue = new MutationQueue(store, { apply: applier }, { operationIdFactory: opIdFactory });

        applier.mockResolvedValueOnce('retryable');
        const controller = new SyncStatusController(queue, () => undefined, () => true);
        await queue.enqueue('task', 7, 'update', { title: 'Do it' });
        await controller.sync();
        expect(controller.getStatus().state).toBe('retrying');

        applier.mockResolvedValue('applied');
        await controller.retry();
        expect(controller.getStatus().state).toBe('saved');
        expect(store.applied.length).toBe(1);
    });

    it('refresh reflects connectivity changes', () => {
        const queue = makeQueue();
        const sink = vi.fn();
        let online = true;
        const controller = new SyncStatusController(queue, sink, () => online);

        online = false;
        controller.refresh();
        expect(controller.getStatus().state).toBe('offline');

        online = true;
        controller.refresh();
        expect(controller.getStatus().state).toBe('online');
    });
});