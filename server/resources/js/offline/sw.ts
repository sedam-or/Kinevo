/**
 * Service Worker entry (TASK-050, offline-sync.md §Service Worker).
 *
 * Wires the browser Service Worker globals (`self`, `self.caches`,
 * `self.clients`, `self.fetch`) to the testable `installShellCaching` core.
 * The precache manifest is injected at build time by the Vite plugin
 * (`__SHELL_PRECACHE__` placeholder replaced with the hashed asset URLs).
 *
 * The SW ONLY caches the app shell and serves navigations offline. It is NOT
 * a business-logic engine: API requests pass through untouched (they are
 * handled by the mutation queue + IndexedDB).
 */
import { installShellCaching, type PrecacheEntry } from './sw-core';
import type { KinevoServiceWorkerGlobalScope } from './sw-env';

const shellPrecache: PrecacheEntry[] =
    typeof __SHELL_PRECACHE__ !== 'undefined' ? __SHELL_PRECACHE__ : [];

const swSelf = self as unknown as KinevoServiceWorkerGlobalScope;

installShellCaching(
    {
        addEventListener: (type, listener) => swSelf.addEventListener(type, listener),
        skipWaiting: () => swSelf.skipWaiting(),
        clients: { claim: () => swSelf.clients.claim() },
        caches: swSelf.caches,
        fetch: (request) => swSelf.fetch(request),
        location: { origin: swSelf.location.origin },
    },
    {
        cacheName: 'kinevo-shell-v1',
        precacheEntries: shellPrecache,
    },
);

export {};
