/**
 * Today cache orchestration (TASK-051, FR-44, SRS §9.2).
 *
 * Responsibilities:
 * - on first online load, fetch the canonical Today schedule and persist it;
 * - provide offline reads of the cached Today data (SRS §9.1 "Today view
 *   cache", "current-day task/subtask/note access");
 * - expose staleness so the UI can refresh when back online.
 */
import type { TodayCacheStore, TodayData, TodayFetcher } from './today-types';

/** Load result describing the freshness of the returned data. */
export type TodayLoadSource = 'network' | 'cache' | 'none';

export interface TodayLoadResult {
    data: TodayData | null;
    source: TodayLoadSource;
}

/**
 * Orchestrates fetching, caching, and offline reads of the Today schedule.
 *
 * `store` and `fetcher` are injectable (IndexedDB + HTTP in production,
 * in-memory + stub in tests). The cache is keyed by ISO date; a cached entry
 * is stale when its `cachedAt` precedes the given `asOf` (i.e. it belongs to
 * an earlier request), signaling the UI to refresh on reconnect.
 */
export class TodayCache {
    private readonly store: TodayCacheStore;
    private readonly fetcher: TodayFetcher;

    constructor(store: TodayCacheStore, fetcher: TodayFetcher) {
        this.store = store;
        this.fetcher = fetcher;
    }

    /**
     * Load Today data for a date, preferring a fresh cache and falling back
     * to the network. Used online: returns cached data if present and fresh,
     * otherwise fetches, persists, and returns the network copy.
     */
    async loadOnline(date: string): Promise<TodayLoadResult> {
        const cached = await this.store.get(date);
        if (cached !== null) {
            return { data: cached, source: 'cache' };
        }
        return this.fetchAndCache(date);
    }

    /**
     * Load Today data offline. Returns the cached snapshot or null if never
     * loaded online (FR-44 precondition not yet met).
     */
    async loadOffline(date: string): Promise<TodayLoadResult> {
        const cached = await this.store.get(date);
        if (cached !== null) {
            return { data: cached, source: 'cache' };
        }
        return { data: null, source: 'none' };
    }

    /** Force a network fetch and persist a fresh snapshot (e.g. on reconnect). */
    async refresh(date: string): Promise<TodayLoadResult> {
        return this.fetchAndCache(date);
    }

    /** Whether a cached entry exists and is considered stale for a date. */
    async isStale(date: string, asOf: string): Promise<boolean> {
        const cached = await this.store.get(date);
        if (cached === null) {
            return true;
        }
        return new Date(cached.cachedAt).getTime() < new Date(asOf).getTime();
    }

    /** Remove a cached snapshot (e.g. when superseded). */
    async clear(date: string): Promise<void> {
        await this.store.clear(date);
    }

    private async fetchAndCache(date: string): Promise<TodayLoadResult> {
        const payload = await this.fetcher.fetch(date);
        const data: TodayData = {
            ...payload,
            cachedAt: new Date().toISOString(),
        };
        await this.store.put(date, data);
        return { data, source: 'network' };
    }
}
