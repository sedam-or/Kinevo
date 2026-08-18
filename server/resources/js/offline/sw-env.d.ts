/**
 * Service Worker build-time environment declarations (TASK-050).
 *
 * Avoids requiring the `WebWorker` TS lib for the whole project. The SW entry
 * uses a minimal structural type for the worker global and a build-time
 * placeholder for the precache manifest.
 */
import type { PrecacheEntry } from './sw-core';

/** Minimal structural view of the ServiceWorkerGlobalScope we consume. */
export interface KinevoServiceWorkerGlobalScope {
    addEventListener(type: string, listener: EventListenerOrEventListenerObject | null): void;
    skipWaiting(): void;
    clients: { claim(): Promise<unknown> };
    caches: import('./sw-core').CacheStorageLike;
    fetch(input: RequestInfo | URL): Promise<Response>;
    location: { origin: string };
}

/** Global placeholder injected at build time by the Vite precache plugin. */
declare global {
    // eslint-disable-next-line no-var
    var __SHELL_PRECACHE__: PrecacheEntry[] | undefined;
}

export {};
