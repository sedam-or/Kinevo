import { describe, expect, it, vi } from 'vitest';
import { TodayCache } from '../today-cache';
import type { TodayCacheStore, TodayData, TodayFetcher } from '../today-types';

/** In-memory TodayCacheStore for tests (replaces IndexedDB absent in happy-dom). */
class MemoryTodayCacheStore implements TodayCacheStore {
    entries = new Map<string, TodayData>();

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

function makeFetcher(payload: Omit<TodayData, 'cachedAt'>): TodayFetcher {
    return { fetch: vi.fn().mockResolvedValue(payload) };
}

function samplePayload(overrides: Partial<Omit<TodayData, 'cachedAt'>> = {}): Omit<TodayData, 'cachedAt'> {
    return {
        date: '2026-08-18',
        tasks: [{ id: 1, title: 'Ship feature', status: 'scheduled', priorityTier: 1 }],
        subtasks: [],
        slots: [{ start: '09:00', end: '10:00', kind: 'assigned', taskId: 1, title: 'Ship feature' }],
        ...overrides,
    };
}

describe('TodayCache', () => {
    it('fetches and caches Today data on first online load', async () => {
        const store = new MemoryTodayCacheStore();
        const payload = samplePayload();
        const cache = new TodayCache(store, makeFetcher(payload));

        const result = await cache.loadOnline('2026-08-18');

        expect(result.source).toBe('network');
        expect(result.data?.tasks[0].title).toBe('Ship feature');
        expect(store.entries.has('2026-08-18')).toBe(true);
        expect(store.entries.get('2026-08-18')?.cachedAt).toBeDefined();
    });

    it('serves a fresh cached snapshot without hitting the network', async () => {
        const store = new MemoryTodayCacheStore();
        const payload = samplePayload();
        const fetcher = makeFetcher(payload);
        const cache = new TodayCache(store, fetcher);

        await cache.loadOnline('2026-08-18');
        const second = await cache.loadOnline('2026-08-18');

        expect(second.source).toBe('cache');
        expect(fetcher.fetch).toHaveBeenCalledTimes(1);
    });

    it('reads Today data offline from cache', async () => {
        const store = new MemoryTodayCacheStore();
        const cache = new TodayCache(store, makeFetcher(samplePayload()));

        await cache.loadOnline('2026-08-18');
        const offline = await cache.loadOffline('2026-08-18');

        expect(offline.source).toBe('cache');
        expect(offline.data?.tasks[0].id).toBe(1);
    });

    it('returns none offline when never loaded online', async () => {
        const store = new MemoryTodayCacheStore();
        const cache = new TodayCache(store, makeFetcher(samplePayload()));

        const offline = await cache.loadOffline('2026-08-18');

        expect(offline.source).toBe('none');
        expect(offline.data).toBeNull();
    });

    it('refresh forces a network fetch and overwrites the snapshot', async () => {
        const store = new MemoryTodayCacheStore();
        const first = samplePayload({ tasks: [{ id: 1, title: 'Old', status: 'scheduled', priorityTier: 1 }] });
        const second = samplePayload({ tasks: [{ id: 1, title: 'New', status: 'scheduled', priorityTier: 1 }] });
        const fetcher = makeFetcher(first);
        const cache = new TodayCache(store, fetcher);

        await cache.loadOnline('2026-08-18');
        (fetcher.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(second);
        const refreshed = await cache.refresh('2026-08-18');

        expect(refreshed.source).toBe('network');
        expect(refreshed.data?.tasks[0].title).toBe('New');
        expect(store.entries.get('2026-08-18')?.tasks[0].title).toBe('New');
    });

    it('reports stale when cachedAt precedes the asOf reference', async () => {
        const store = new MemoryTodayCacheStore();
        const cache = new TodayCache(store, makeFetcher(samplePayload()));

        await cache.loadOnline('2026-08-18');
        // cachedAt was set "now"; a reference in the future makes it stale.
        const stale = await cache.isStale('2026-08-18', '2099-01-01T00:00:00.000Z');
        const fresh = await cache.isStale('2026-08-18', '2000-01-01T00:00:00.000Z');

        expect(stale).toBe(true);
        expect(fresh).toBe(false);
    });

    it('reports stale when nothing is cached', async () => {
        const store = new MemoryTodayCacheStore();
        const cache = new TodayCache(store, makeFetcher(samplePayload()));

        expect(await cache.isStale('2026-08-18', new Date().toISOString())).toBe(true);
    });

    it('clear removes the cached snapshot', async () => {
        const store = new MemoryTodayCacheStore();
        const cache = new TodayCache(store, makeFetcher(samplePayload()));

        await cache.loadOnline('2026-08-18');
        await cache.clear('2026-08-18');

        expect(store.entries.has('2026-08-18')).toBe(false);
        expect((await cache.loadOffline('2026-08-18')).source).toBe('none');
    });
});
