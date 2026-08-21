/**
 * Offline UAT (TASK-151) — user-acceptance integration test.
 *
 * Mirrors the real production wiring in `AuthHost.vue`:
 *   MutationQueue (offline/queue-store in-memory) + HttpMutationApplier
 *   (apiClient, scripted here) + SyncStatusController + TodayCache.
 *
 * IndexedDB / Service Worker are absent in happy-dom, so they are replaced by
 * the injectable in-memory stores the production code already accepts (the same
 * seam used by every other offline test). This exercises the *user-visible*
 * offline flow end to end, not just isolated units:
 *
 *   load Today online  -> disconnect -> open Today (cache)
 *   -> Quick Capture   -> edit task  -> queue mutation
 *   -> reconnect       -> sync       -> verify server state
 *
 * Plus: offline conflict, version conflict, retry, permanent failure.
 */
import { describe, expect, it, vi } from 'vitest';
import { MutationQueue } from '../queue';
import { SyncStatusController } from '../sync-status';
import { TodayCache } from '../today-cache';
import type {
    ApplyOutcome,
    MutationEnvelope,
    OfflineMutationStore,
    OfflineOperationApplier,
} from '../queue-types';
import type { TodayCacheStore, TodayData, TodayFetcher } from '../today-types';

const TODAY_DATE = '2026-08-18';

/** In-memory queue store; also exposes everything still held (for preservation checks). */
class MemoryQueueStore implements OfflineMutationStore {
    private mutations = new Map<string, MutationEnvelope>();

    async enqueue(envelope: MutationEnvelope): Promise<void> {
        this.mutations.set(envelope.operationId, envelope);
    }

    async listPending(): Promise<MutationEnvelope[]> {
        return [...this.mutations.values()]
            .filter(
                (m) =>
                    m.status === 'queued' ||
                    m.status === 'syncing' ||
                    m.status === 'failed_retryable',
            )
            .sort(
                (a, b) =>
                    new Date(a.clientTimestamp).getTime() - new Date(b.clientTimestamp).getTime(),
            );
    }

    async markSyncing(operationId: string): Promise<void> {
        const m = this.mutations.get(operationId);
        if (m) {
            this.mutations.set(operationId, { ...m, status: 'syncing' });
        }
    }

    async markApplied(operationId: string): Promise<void> {
        this.mutations.delete(operationId);
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

    /** Every envelope still retained by the store (not yet successfully applied). */
    preserved(): MutationEnvelope[] {
        return [...this.mutations.values()];
    }
}

/** Applier that records every applied envelope and replays a scripted outcome sequence. */
class ScriptedApplier implements OfflineOperationApplier {
    sequence: ApplyOutcome[] = [];
    readonly received: MutationEnvelope[] = [];

    constructor(initial: ApplyOutcome[] = []) {
        this.sequence = [...initial];
    }

    apply = vi.fn(async (envelope: MutationEnvelope): Promise<ApplyOutcome> => {
        this.received.push(envelope);
        return this.sequence.shift() ?? 'applied';
    });
}

class MemoryTodayCacheStore implements TodayCacheStore {
    private entries = new Map<string, TodayData>();

    async put(date: string, data: TodayData): Promise<void> {
        this.entries.set(date, data);
    }

    async get(date: string): Promise<TodayData | null> {
        return this.entries.get(date) ?? null;
    }

    async clear(date: string): Promise<void> {
        this.entries.delete(date);
    }
}

function samplePayload(overrides: Partial<Omit<TodayData, 'cachedAt'>> = {}): Omit<TodayData, 'cachedAt'> {
    return {
        date: TODAY_DATE,
        tasks: [{ id: 1, title: 'Ship feature', status: 'scheduled', priorityTier: 1 }],
        subtasks: [],
        slots: [{ start: '09:00', end: '10:00', kind: 'assigned', taskId: 1, title: 'Ship feature' }],
        ...overrides,
    };
}

function makeFetcher(payload: Omit<TodayData, 'cachedAt'>): TodayFetcher {
    return { fetch: vi.fn().mockResolvedValue(payload) };
}

/** Production-shaped stack (AuthHost wiring) with injectable in-memory backends. */
function buildStack(online: { value: boolean }, outcomes: ApplyOutcome[] = []) {
    const store = new MemoryQueueStore();
    const applier = new ScriptedApplier(outcomes);
    const queue = new MutationQueue(store, applier, {
        operationIdFactory: () => `op-${Math.random().toString(36).slice(2)}`,
    });
    const sink = vi.fn();
    const controller = new SyncStatusController(queue, sink, () => online.value);

    const todayStore = new MemoryTodayCacheStore();
    const fetcher = makeFetcher(samplePayload());
    const todayCache = new TodayCache(todayStore, fetcher);

    return { store, applier, queue, sink, controller, todayStore, fetcher, todayCache };
}

describe('TASK-151 Offline UAT — golden journey', () => {
    it('loads Today online, goes offline, queues mutations, reconnects, syncs, and verifies server state', async () => {
        const online = { value: true };
        const stack = buildStack(online);

        // 1. load Today online -> fetched from network and cached (FR-44 baseline).
        const first = await stack.todayCache.loadOnline(TODAY_DATE);
        expect(first.source).toBe('network');
        expect(first.data?.tasks[0].title).toBe('Ship feature');
        expect(stack.todayStore.get(TODAY_DATE)).resolves.toBeDefined();

        // 2. disconnect.
        online.value = false;

        // 3. open Today offline -> served from cache (primary surface still works).
        const offline = await stack.todayCache.loadOffline(TODAY_DATE);
        expect(offline.source).toBe('cache');
        expect(offline.data?.tasks[0].id).toBe(1);

        // 4. Quick Capture while offline -> queued locally.
        await stack.queue.enqueue('quick_capture', 0, 'create', {
            title: 'Offline capture',
            priorityTier: 1,
            estimatedMinutes: 30,
        });
        expect(stack.controller.getStatus().state).toBe('queued');
        expect(stack.controller.getStatus().queuedCount).toBe(1);

        // 5. edit task while offline -> second queued mutation.
        await stack.queue.enqueue('task', 1, 'update', { title: 'Edited offline' });
        expect(stack.controller.getStatus().queuedCount).toBe(2);

        // visible sync state is 'queued' (not 'offline'), with a human explanation.
        const status = stack.controller.getStatus();
        expect(status.state).toBe('queued');
        expect(status.explanation.length).toBeGreaterThan(0);

        // 6. reconnect.
        online.value = true;

        // 7. sync -> both mutations applied; verify the server received them.
        await stack.controller.sync();
        expect(stack.applier.received).toHaveLength(2);
        expect(stack.applier.received[0].entityType).toBe('quick_capture');
        expect(stack.applier.received[0].payload).toMatchObject({ title: 'Offline capture' });
        expect(stack.applier.received[1].entityType).toBe('task');
        expect(stack.applier.received[1].payload).toMatchObject({ title: 'Edited offline' });

        // 8. queue drained, visible state is 'saved' (SRS §9.1 "synced").
        expect(stack.queue.getState()).toBe('idle');
        expect(stack.controller.getStatus().state).toBe('saved');
        expect(stack.controller.getStatus().queuedCount).toBe(0);
        expect(stack.store.preserved()).toHaveLength(0);

        // 9. Today re-fetches on reconnect to reflect the synced changes.
        (stack.fetcher.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(
            samplePayload({ tasks: [{ id: 1, title: 'Edited offline', status: 'scheduled', priorityTier: 1 }] }),
        );
        const refreshed = await stack.todayCache.refresh(TODAY_DATE);
        expect(refreshed.source).toBe('network');
        expect(refreshed.data?.tasks[0].title).toBe('Edited offline');
    });
});

describe('TASK-151 Offline UAT — conflict handling', () => {
    it('surfaces an offline conflict and preserves the local mutation (FR-57 conservative rule)', async () => {
        const online = { value: true };
        const stack = buildStack(online, ['conflict']);

        // A non-versioned task edit collides with the server (e.g. 409).
        await stack.queue.enqueue('task', 1, 'update', { title: 'Local only' });
        await stack.controller.sync();

        const status = stack.controller.getStatus();
        expect(status.state).toBe('conflict');
        expect(status.retryable).toBe(false);
        expect(status.explanation.toLowerCase()).toContain('conflict');
        // local data is NOT silently discarded.
        expect(stack.store.preserved()).toHaveLength(1);
    });

    it('surfaces a version conflict (stale base_version) and retains the local note', async () => {
        const online = { value: true };
        const stack = buildStack(online, ['conflict']);

        // Versioned rich content (note) with a stale base_version -> 409.
        await stack.queue.enqueue('note', 5, 'update', { title: 'My note', document_json: {} }, 2);
        await stack.controller.sync();

        expect(stack.controller.getStatus().state).toBe('conflict');
        const preserved = stack.store.preserved();
        expect(preserved).toHaveLength(1);
        expect(preserved[0].entityType).toBe('note');
        expect(preserved[0].baseVersion).toBe(2);
        expect(preserved[0].payload).toMatchObject({ title: 'My note' });
    });
});

describe('TASK-151 Offline UAT — retry and permanent failure', () => {
    it('marks a transient failure as retrying and recovers on manual retry', async () => {
        const online = { value: true };
        const stack = buildStack(online, ['retryable']);

        await stack.queue.enqueue('task', 1, 'update', { title: 'Flaky' });
        await stack.controller.sync();

        expect(stack.controller.getStatus().state).toBe('retrying');
        expect(stack.controller.getStatus().retryable).toBe(true);
        expect(stack.store.preserved()).toHaveLength(1);

        // network recovers -> manual retry applies the mutation.
        stack.applier.sequence = ['applied'];
        await stack.controller.retry();

        expect(stack.controller.getStatus().state).toBe('saved');
        expect(stack.store.preserved()).toHaveLength(0);
        expect(stack.applier.received).toHaveLength(2);
    });

    it('marks a permanent failure as failed and preserves the local copy', async () => {
        const online = { value: true };
        const stack = buildStack(online, ['permanent']);

        // e.g. a 422 validation error on a non-versioned mutation.
        await stack.queue.enqueue('task', 1, 'update', { title: '' });
        await stack.controller.sync();

        const status = stack.controller.getStatus();
        expect(status.state).toBe('failed');
        expect(status.retryable).toBe(true);
        expect(status.error).toBeTruthy();
        // local data preserved for the user to fix and resync.
        expect(stack.store.preserved()).toHaveLength(1);
    });
});
