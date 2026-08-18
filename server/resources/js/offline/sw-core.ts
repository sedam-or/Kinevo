/**
 * Service Worker shell-cache strategy core (TASK-050, offline-sync.md
 * §Service Worker, FR-44).
 *
 * The Service Worker caches the app shell (HTML/CSS/JS/fonts) so offline
 * navigation works within scope. It MUST NOT become a second business-logic
 * engine: it only serves cached static shell assets and navigation, and never
 * intercepts business API calls (those go through the mutation queue +
 * IndexedDB, not the SW).
 *
 * This module is framework-agnostic and browser-agnostic: it receives the SW
 * browser primitives (self, fetch, caches, Request/Response) as dependencies,
 * so the strategy logic is unit-testable in isolation (happy-dom has no real
 * Service Worker / Cache Storage).
 */

/** A shell asset URL to precache on install (Vite hashed assets + fonts). */
export interface PrecacheEntry {
    url: string;
    revision: string;
}

/** Browser primitives the SW core needs, injectable for tests. */
export interface ServiceWorkerEnvironment {
    addEventListener(type: 'install' | 'activate' | 'fetch', listener: (event: unknown) => void): void;
    skipWaiting(): void;
    clients: { claim(): Promise<unknown> };
    caches: CacheStorageLike;
    fetch(request: Request | string): Promise<Response>;
    location: { origin: string };
}

/** Minimal CacheStorage/Cache surface. */
export interface CacheStorageLike {
    open(name: string): Promise<CacheLike>;
    keys(): Promise<string[]>;
    delete(name: string): Promise<boolean>;
}

export interface CacheLike {
    addAll(urls: string[]): Promise<void>;
    match(request: Request | string): Promise<Response | undefined>;
    put(request: Request | string, response: Response): Promise<void>;
    delete(request: Request | string): Promise<boolean>;
}

export interface CacheStrategyOptions {
    cacheName: string;
    precacheEntries: PrecacheEntry[];
    /** Navigation request predicate; defaults to same-origin GET navigations. */
    isNavigation?: (request: Request) => boolean;
    /** Asset request predicate; defaults to same-origin GET with an accept of css/js/font. */
    isShellAsset?: (request: Request) => boolean;
}

const INSTALL_EVENT = 'install';
const ACTIVATE_EVENT = 'activate';
const FETCH_EVENT = 'fetch';

/**
 * Binds the shell-cache strategy to a Service Worker environment.
 *
 * - install: precache all shell assets; on failure, still finish (best effort).
 * - activate: claim clients and clean up stale caches.
 * - fetch:
 *   - navigations → network-first, falling back to cached shell (offline shell).
 *   - shell assets → cache-first (fast, offline-capable).
 *   - anything else (incl. API) → pass through untouched (never a business
 *     engine; offline-sync.md §Service Worker).
 */
export function installShellCaching(env: ServiceWorkerEnvironment, options: CacheStrategyOptions): void {
    const isNavigation = options.isNavigation ?? defaultIsNavigation;
    const isShellAsset = options.isShellAsset ?? defaultIsShellAsset;

    env.addEventListener(INSTALL_EVENT, (event) => {
        const installEvent = event as {
            waitUntil(promise: Promise<unknown>): void;
        };
        installEvent.waitUntil(
            env.caches
                .open(options.cacheName)
                .then((cache) =>
                    cache.addAll(options.precacheEntries.map((entry) => new URL(entry.url, env.location.origin).href)),
                )
                .catch(() => {
                    // Best-effort precache: an unavailable asset must not
                    // block install/activation of the app shell.
                }),
        );
    });

    env.addEventListener(ACTIVATE_EVENT, (event) => {
        const activateEvent = event as {
            waitUntil(promise: Promise<unknown>): void;
        };
        activateEvent.waitUntil(
            (async () => {
                await env.clients.claim();
                const keys = await env.caches.keys();
                await Promise.all(
                    keys
                        .filter((name) => name !== options.cacheName)
                        .map((name) => env.caches.delete(name)),
                );
            })(),
        );
    });

    env.addEventListener(FETCH_EVENT, (event) => {
        const fetchEvent = event as {
            request: Request;
            respondWith(promise: Promise<Response>): void;
        };
        const request = fetchEvent.request;

        if (!isSameOrigin(request, env.location.origin)) {
            return; // never proxy cross-origin (fonts/CDN may still be needed)
        }

        if (isNavigation(request)) {
            fetchEvent.respondWith(networkFirst(env, request, options.cacheName));
            return;
        }

        if (isShellAsset(request)) {
            fetchEvent.respondWith(cacheFirst(env, request, options.cacheName));
            return;
        }

        // Business API and anything else: pass through, never cached here.
    });
}

/** Network-first with cache fallback — used for navigations (offline shell). */
export async function networkFirst(
    env: ServiceWorkerEnvironment,
    request: Request,
    cacheName: string,
): Promise<Response> {
    try {
        const response = await env.fetch(request);
        if (response && response.ok) {
            const cache = await env.caches.open(cacheName);
            await cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cache = await env.caches.open(cacheName);
        const cached = await cache.match(request);
        if (cached) {
            return cached;
        }
        throw new Error('Network unavailable and no cached shell response.');
    }
}

/** Cache-first with network fallback — used for hashed shell assets. */
export async function cacheFirst(
    env: ServiceWorkerEnvironment,
    request: Request,
    cacheName: string,
): Promise<Response> {
    const cache = await env.caches.open(cacheName);
    const cached = await cache.match(request);
    if (cached) {
        return cached;
    }
    const response = await env.fetch(request);
    if (response && response.ok) {
        await cache.put(request, response.clone());
    }
    return response;
}

function isSameOrigin(request: Request, origin: string): boolean {
    try {
        return new URL(request.url).origin === origin;
    } catch {
        return false;
    }
}

function defaultIsNavigation(request: Request): boolean {
    return request.method === 'GET' && request.mode === 'navigate';
}

function defaultIsShellAsset(request: Request): boolean {
    if (request.method !== 'GET') {
        return false;
    }
    const url = new URL(request.url);
    return /\.(?:js|css|woff2?)$/i.test(url.pathname);
}
