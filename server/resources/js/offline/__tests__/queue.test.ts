import { describe, expect, it, vi } from 'vitest';
import { MutationQueue } from '../queue';
import type {
    ApplyOutcome,
    MutationEnvelope,
    OfflineMutationStore,
    OfflineOperationApplier,
} from '../queue-types';

/** In-memory OfflineMutationStore for tests (replaces IndexedDB absent in happy-dom). */
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

function makeApplier(outcome: ApplyOutcome | ((envelope: MutationEnvelope) => ApplyOutcome)): OfflineOperationApplier {
    return {
        apply: vi.fn(async (envelope) =>
            typeof outcome === 'function' ? outcome(envelope) : outcome,
        ),
    };
}

let idCounter = 0;
function opIdFactory(): string {
    idCounter += 1;
    return `op-${idCounter}`;
}

describe('MutationQueue', () => {
    it('starts idle with zero queued', () => {
        const queue = new MutationQueue(new MemoryQueueStore(), makeApplier('applied'));
        expect(queue.getState()).toBe('idle');
        expect(queue.getQueuedCount()).toBe(0);
    });

    it('enqueue persists an envelope and transitions to queued', async () => {
        const store = new MemoryQueueStore();
        const queue = new MutationQueue(store, makeApplier('applied'), { operationIdFactory: opIdFactory });

        await queue.enqueue('task', 7, 'update', { title: 'Do it' }, 3);

        expect(store.mutations.size).toBe(1);
        const envelope = [...store.mutations.values()][0];
        expect(envelope.entityType).toBe('task');
        expect(envelope.entityId).toBe(7);
        expect(envelope.operationType).toBe('update');
        expect(envelope.baseVersion).toBe(3);
        expect(envelope.status).toBe('queued');
        expect(queue.getState()).toBe('queued');
        expect(queue.getQueuedCount()).toBe(1);
    });

    it('sync applies queued mutations in FIFO order', async () => {
        const store = new MemoryQueueStore();
        const applied: number[] = [];
        const applier: OfflineOperationApplier = {
            apply: vi.fn(async (envelope): Promise<ApplyOutcome> => {
                applied.push(envelope.entityId);
                return 'applied';
            }),
        };
        const queue = new MutationQueue(store, applier, { operationIdFactory: opIdFactory });

        await queue.enqueue('task', 1, 'create', {});
        await queue.enqueue('task', 2, 'create', {});

        const state = await queue.sync();

        expect(state).toBe('idle');
        expect(applied).toEqual([1, 2]); // FIFO
        expect(queue.getQueuedCount()).toBe(0);
    });

    it('collapses consecutive non-versioned mutations to the same entity (last-write-wins)', async () => {
        const store = new MemoryQueueStore();
        const applied: string[] = [];
        const applier: OfflineOperationApplier = {
            apply: vi.fn(async (envelope): Promise<ApplyOutcome> => {
                applied.push(envelope.payload.title as string);
                return 'applied';
            }),
        };
        const queue = new MutationQueue(store, applier, { operationIdFactory: opIdFactory });

        await queue.enqueue('task', 5, 'update', { title: 'v1' });
        await queue.enqueue('task', 5, 'update', { title: 'v2' });

        // Collapse removes the earlier duplicate; only the latest remains.
        expect(store.mutations.size).toBe(1);
        const remaining = [...store.mutations.values()][0];
        expect(remaining.payload.title).toBe('v2');

        await queue.sync();
        expect(applied).toEqual(['v2']); // only the newest applied
    });

    it('never collapses versioned mutations (conservative rule, SRS §9.4)', async () => {
        const store = new MemoryQueueStore();
        const applier: OfflineOperationApplier = {
            apply: vi.fn(async (): Promise<ApplyOutcome> => 'applied'),
        };
        const queue = new MutationQueue(store, applier, { operationIdFactory: opIdFactory });

        await queue.enqueue('note', 9, 'update', { content: 'a' }, 1);
        await queue.enqueue('note', 9, 'update', { content: 'b' }, 2);

        // Versioned (rich content) mutations are NOT collapsed.
        expect(store.mutations.size).toBe(2);
    });

    it('retryable failure keeps the mutation and reports failed_retryable', async () => {
        const store = new MemoryQueueStore();
        const queue = new MutationQueue(store, makeApplier('retryable'), { operationIdFactory: opIdFactory });

        await queue.enqueue('task', 1, 'create', {});
        const state = await queue.sync();

        expect(state).toBe('failed_retryable');
        const pending = await store.listPending();
        expect(pending.length).toBe(1);
        expect(pending[0].status).toBe('failed_retryable');
    });

    it('subsequent sync retries retryable failures', async () => {
        const store = new MemoryQueueStore();
        let call = 0;
        const applier: OfflineOperationApplier = {
            apply: vi.fn(async (): Promise<ApplyOutcome> => {
                call += 1;
                return call === 1 ? 'retryable' : 'applied';
            }),
        };
        const queue = new MutationQueue(store, applier, { operationIdFactory: opIdFactory });

        await queue.enqueue('task', 1, 'create', {});
        await queue.sync();
        const state = await queue.sync();

        expect(state).toBe('idle');
        expect(call).toBe(2);
    });

    it('conflict preserves the mutation and surfaces conflict without retrying', async () => {
        const store = new MemoryQueueStore();
        const queue = new MutationQueue(store, makeApplier('conflict'), { operationIdFactory: opIdFactory });

        const states: string[] = [];
        queue.subscribe((s) => states.push(s.state));

        await queue.enqueue('note', 9, 'update', { content: 'x' }, 4);
        const state = await queue.sync();

        expect(state).toBe('conflict');
        expect(states).toContain('conflict');
        const preserved = [...store.mutations.values()];
        expect(preserved.length).toBe(1);
        expect(preserved[0].status).toBe('failed_permanent');
        expect((await store.listPending()).length).toBe(0); // not retried
    });

    it('permanent failure surfaces failed_permanent', async () => {
        const store = new MemoryQueueStore();
        const queue = new MutationQueue(store, makeApplier('permanent'), { operationIdFactory: opIdFactory });

        await queue.enqueue('task', 1, 'create', {});
        const state = await queue.sync();

        expect(state).toBe('failed_permanent');
    });

    it('resolve discards a conflict after reconciliation', async () => {
        const store = new MemoryQueueStore();
        const queue = new MutationQueue(store, makeApplier('conflict'), { operationIdFactory: opIdFactory });

        await queue.enqueue('note', 9, 'update', { content: 'x' }, 4);
        await queue.sync();

        const operationId = [...store.mutations.keys()][0];
        await queue.resolve(operationId);

        expect(store.mutations.size).toBe(0);
    });
});
