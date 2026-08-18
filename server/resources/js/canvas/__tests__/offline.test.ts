import { describe, expect, it, vi } from 'vitest';
import { CanvasOfflineQueue } from '../offline-queue';
import { OfflineAwarePersistence } from '../offline-persistence';
import type { ApplyOutcome, MutationEnvelope, MutationStore, OfflineOperationApplier } from '../offline';
import type { CanvasPersistence, CanvasSaveResponse } from '../autosave';
import type { CanvasScene } from '../types';

function makeApplier(outcome: ApplyOutcome | ((envelope: MutationEnvelope) => ApplyOutcome)): OfflineOperationApplier {
    return {
        apply: vi.fn(async (envelope) =>
            typeof outcome === 'function' ? outcome(envelope) : outcome,
        ),
    };
}

/**
 * In-memory MutationStore for tests (replaces IndexedDB which is absent in
 * happy-dom). Mirrors the queue/snapshot semantics of the IndexedDB store.
 */
class MemoryMutationStore implements MutationStore {
    mutations = new Map<string, MutationEnvelope>();
    snapshots = new Map<number, { scene: unknown; baseVersion: number }>();
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

    async saveSnapshot(canvasId: number, scene: unknown, baseVersion: number): Promise<void> {
        this.snapshots.set(canvasId, { scene, baseVersion });
    }

    async getSnapshot(canvasId: number): Promise<{ scene: unknown; baseVersion: number } | null> {
        return this.snapshots.get(canvasId) ?? null;
    }
}

const scene: CanvasScene = { elements: [{ id: 'a', type: 'rectangle' }], appState: {} };

let idCounter = 0;
function opIdFactory(): string {
    idCounter += 1;
    return `op-${idCounter}`;
}

describe('CanvasOfflineQueue', () => {
    it('starts idle with zero queued', () => {
        const queue = new CanvasOfflineQueue(new MemoryMutationStore(), makeApplier('applied'), {});
        expect(queue.getState()).toBe('idle');
        expect(queue.getQueuedCount()).toBe(0);
    });

    it('enqueue persists a snapshot + envelope and transitions to queued', async () => {
        const store = new MemoryMutationStore();
        const queue = new CanvasOfflineQueue(store, makeApplier('applied'), { operationIdFactory: opIdFactory });

        await queue.enqueue(42, 3, scene);

        expect(store.snapshots.get(42)).toEqual({ scene, baseVersion: 3 });
        expect(store.mutations.size).toBe(1);
        const envelope = [...store.mutations.values()][0];
        expect(envelope.operationId).toBe('op-1');
        expect(envelope.entityType).toBe('canvas');
        expect(envelope.entityId).toBe(42);
        expect(envelope.baseVersion).toBe(3);
        expect(envelope.status).toBe('queued');
        expect(queue.getState()).toBe('queued');
        expect(queue.getQueuedCount()).toBe(1);
    });

    it('sync applies queued mutations in FIFO order', async () => {
        const store = new MemoryMutationStore();
        const applied: number[] = [];
        const applier: OfflineOperationApplier = {
            apply: vi.fn(async (envelope): Promise<ApplyOutcome> => {
                applied.push(envelope.baseVersion ?? 0);
                return 'applied';
            }),
        };
        const queue = new CanvasOfflineQueue(store, applier, { operationIdFactory: opIdFactory });

        await queue.enqueue(42, 1, scene);
        await queue.enqueue(42, 2, scene);

        const state = await queue.sync();

        expect(state).toBe('idle');
        expect(applied).toEqual([1, 2]); // FIFO
        expect(queue.getQueuedCount()).toBe(0);
        expect(store.mutations.size).toBe(0);
    });

    it('version conflict preserves the mutation and surfaces conflict without retrying', async () => {
        const store = new MemoryMutationStore();
        const applier = makeApplier('conflict');
        const queue = new CanvasOfflineQueue(store, applier, { operationIdFactory: opIdFactory });

        const states: string[] = [];
        queue.subscribe((s) => states.push(s.state));

        await queue.enqueue(42, 3, scene);
        const state = await queue.sync();

        expect(state).toBe('conflict');
        expect(states).toContain('conflict');
        // Conflict data is preserved for reconciliation (offline-sync.md), not
        // silently discarded and not retried.
        const preserved = [...store.mutations.values()];
        expect(preserved.length).toBe(1);
        expect(preserved[0].status).toBe('failed_permanent');
        const pending = await store.listPending();
        expect(pending.length).toBe(0);
        expect(queue.getQueuedCount()).toBe(0);
    });

    it('retryable failure keeps the mutation queued and reports failed_retryable', async () => {
        const store = new MemoryMutationStore();
        const applier = makeApplier('retryable');
        const queue = new CanvasOfflineQueue(store, applier, { operationIdFactory: opIdFactory });

        await queue.enqueue(42, 1, scene);
        const state = await queue.sync();

        expect(state).toBe('failed_retryable');
        // Retryable mutations remain pending for the next sync attempt.
        const pending = await store.listPending();
        expect(pending.length).toBe(1);
        expect(pending[0].status).toBe('failed_retryable');
    });

    it('subsequent sync retries retryable failures', async () => {
        const store = new MemoryMutationStore();
        let call = 0;
        const applier: OfflineOperationApplier = {
            apply: vi.fn(async (): Promise<ApplyOutcome> => {
                call += 1;
                return call === 1 ? 'retryable' : 'applied';
            }),
        };
        const queue = new CanvasOfflineQueue(store, applier, { operationIdFactory: opIdFactory });

        await queue.enqueue(42, 1, scene);
        await queue.sync();
        const state = await queue.sync();

        expect(state).toBe('idle');
        expect(call).toBe(2);
        expect(store.mutations.size).toBe(0);
    });
});

describe('OfflineAwarePersistence', () => {
    it('queues the mutation when the online save fails with OFFLINE', async () => {
        const store = new MemoryMutationStore();
        const queue = new CanvasOfflineQueue(store, makeApplier('applied'), { operationIdFactory: opIdFactory });
        const offlineError = Object.assign(new Error('offline'), { code: 'OFFLINE' });
        const online: CanvasPersistence = { save: vi.fn().mockRejectedValue(offlineError) };
        const persistence = new OfflineAwarePersistence(online, queue);

        const result = await persistence.save(42, 3, scene);

        expect(result.version).toBe(3); // treated as saved locally
        expect(store.mutations.size).toBe(1);
        expect(store.snapshots.get(42)).toEqual({ scene, baseVersion: 3 });
    });

    it('propagates non-offline failures without queueing', async () => {
        const store = new MemoryMutationStore();
        const queue = new CanvasOfflineQueue(store, makeApplier('applied'), { operationIdFactory: opIdFactory });
        const online: CanvasPersistence = { save: vi.fn().mockRejectedValue(new Error('server error')) };
        const persistence = new OfflineAwarePersistence(online, queue);

        await expect(persistence.save(42, 3, scene)).rejects.toThrow('server error');
        expect(store.mutations.size).toBe(0);
    });

    it('delegates to the online persistence when online', async () => {
        const store = new MemoryMutationStore();
        const queue = new CanvasOfflineQueue(store, makeApplier('applied'), { operationIdFactory: opIdFactory });
        const online: CanvasPersistence = { save: vi.fn().mockResolvedValue({ version: 9 }) };
        const persistence = new OfflineAwarePersistence(online, queue);

        const result: CanvasSaveResponse = await persistence.save(42, 8, scene);

        expect(result.version).toBe(9);
        expect(store.mutations.size).toBe(0);
    });
});
