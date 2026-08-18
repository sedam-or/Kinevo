import { describe, expect, it, vi } from 'vitest';
import {
    cacheFirst,
    installShellCaching,
    networkFirst,
    type CacheLike,
    type CacheStorageLike,
    type ServiceWorkerEnvironment,
} from '../sw-core';

/** In-memory Cache for tests. */
class MemoryCache implements CacheLike {
    entries = new Map<string, Response>();

    async addAll(urls: string[]): Promise<void> {
        for (const url of urls) {
            this.entries.set(url, new Response('cached', { status: 200 }));
        }
    }

    async match(request: Request | string): Promise<Response | undefined> {
        const url = typeof request === 'string' ? request : request.url;
        return this.entries.get(url);
    }

    async put(request: Request | string, response: Response): Promise<void> {
        const url = typeof request === 'string' ? request : request.url;
        this.entries.set(url, response.clone());
    }

    async delete(request: Request | string): Promise<boolean> {
        const url = typeof request === 'string' ? request : request.url;
        return this.entries.delete(url);
    }
}

function makeCacheStorage(): CacheStorageLike & { caches: Map<string, MemoryCache> } {
    const caches = new Map<string, MemoryCache>();
    return {
        caches,
        async open(name: string) {
            if (!caches.has(name)) {
                caches.set(name, new MemoryCache());
            }
            return caches.get(name)!;
        },
        async keys() {
            return [...caches.keys()];
        },
        async delete(name: string) {
            return caches.delete(name);
        },
    };
}

interface EnvCallbacks {
    install?: (event: unknown) => void;
    activate?: (event: unknown) => void;
    fetch?: (event: unknown) => void;
}

function makeEnv(): ServiceWorkerEnvironment & {
    listeners: EnvCallbacks;
    cachesImpl: ReturnType<typeof makeCacheStorage>;
} {
    const cachesImpl = makeCacheStorage();
    const listeners: EnvCallbacks = {};
    const env: ServiceWorkerEnvironment & { listeners: EnvCallbacks; cachesImpl: ReturnType<typeof makeCacheStorage> } = {
        listeners,
        cachesImpl,
        caches: {
            open: cachesImpl.open.bind(cachesImpl),
            keys: cachesImpl.keys.bind(cachesImpl),
            delete: cachesImpl.delete.bind(cachesImpl),
        },
        clients: { claim: vi.fn().mockResolvedValue(undefined) },
        fetch: vi.fn().mockResolvedValue(new Response('network', { status: 200 })),
        skipWaiting: vi.fn(),
        location: { origin: 'https://app.example.com' },
        addEventListener: (type, listener) => {
            (listeners as Record<string, unknown>)[type] = listener;
        },
    };
    return env;
}

function makeRequest(url: string, init?: RequestInit & { forceNavigate?: boolean }): Request {
    const { forceNavigate, ...rest } = init ?? {};
    const request = new Request(url, { ...rest, method: rest.method ?? 'GET' });
    if (forceNavigate) {
        // happy-dom rejects `mode: 'navigate'` on construction; set it after.
        Object.defineProperty(request, 'mode', { value: 'navigate', configurable: true });
    }
    return request;
}

describe('networkFirst', () => {
    it('returns the network response and caches it on success', async () => {
        const env = makeEnv();
        const navRequest = makeRequest('https://app.example.com/', { forceNavigate: true });
        (env.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(
            new Response('shell html', { status: 200 }),
        );

        const response = await networkFirst(env, navRequest, 'kinevo-shell-v1');

        expect(await response.text()).toBe('shell html');
        const cached = await env.cachesImpl.open('kinevo-shell-v1');
        const entry = await cached.match(navRequest);
        expect(entry).toBeDefined();
    });

    it('falls back to cache when the network fails', async () => {
        const env = makeEnv();
        const navRequest = makeRequest('https://app.example.com/', { forceNavigate: true });
        (env.fetch as ReturnType<typeof vi.fn>).mockRejectedValue(new Error('offline'));

        const cache = await env.cachesImpl.open('kinevo-shell-v1');
        await cache.put(navRequest, new Response('cached shell', { status: 200 }));

        const response = await networkFirst(env, navRequest, 'kinevo-shell-v1');
        expect(await response.text()).toBe('cached shell');
    });

    it('throws when offline and no cache is available', async () => {
        const env = makeEnv();
        const navRequest = makeRequest('https://app.example.com/', { forceNavigate: true });
        (env.fetch as ReturnType<typeof vi.fn>).mockRejectedValue(new Error('offline'));

        await expect(networkFirst(env, navRequest, 'kinevo-shell-v1')).rejects.toThrow(
            /Network unavailable/,
        );
    });
});

describe('cacheFirst', () => {
    it('returns a cached shell asset without hitting the network', async () => {
        const env = makeEnv();
        const assetRequest = makeRequest('https://app.example.com/build/assets/app-abc.js');

        const cache = await env.cachesImpl.open('kinevo-shell-v1');
        await cache.put(assetRequest, new Response('cached js', { status: 200 }));

        const response = await cacheFirst(env, assetRequest, 'kinevo-shell-v1');

        expect(await response.text()).toBe('cached js');
        expect(env.fetch).not.toHaveBeenCalled();
    });

    it('fetches and caches a miss', async () => {
        const env = makeEnv();
        const assetRequest = makeRequest('https://app.example.com/build/assets/app-abc.js');
        (env.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(
            new Response('network js', { status: 200 }),
        );

        const response = await cacheFirst(env, assetRequest, 'kinevo-shell-v1');

        expect(await response.text()).toBe('network js');
        const cached = await env.cachesImpl.open('kinevo-shell-v1');
        const entry = await cached.match(assetRequest);
        expect(entry).toBeDefined();
    });
});

describe('installShellCaching', () => {
    it('precaches shell assets on install and does not block on failure', async () => {
        const env = makeEnv();
        const options = {
            cacheName: 'kinevo-shell-v1',
            precacheEntries: [
                { url: '/build/assets/app-abc.js', revision: 'shell' },
                { url: '/build/assets/app-abc.css', revision: 'shell' },
            ],
        };

        installShellCaching(env, options);

        // Trigger install and await the precache promise.
        const pending: Promise<unknown>[] = [];
        const installEvent = {
            waitUntil: (p: Promise<unknown>) => {
                pending.push(p);
                return p;
            },
        };
        const installHandler = env.listeners.install as (e: typeof installEvent) => void;
        installHandler(installEvent);
        await Promise.all(pending);

        const cache = await env.cachesImpl.open('kinevo-shell-v1');
        const js = await cache.match(makeRequest('https://app.example.com/build/assets/app-abc.js'));
        const css = await cache.match(makeRequest('https://app.example.com/build/assets/app-abc.css'));
        expect(js).toBeDefined();
        expect(css).toBeDefined();
    });

    it('claims clients and cleans stale caches on activate', async () => {
        const env = makeEnv();
        // Pre-seed a stale cache and the current shell cache.
        await env.cachesImpl.open('kinevo-stale-v1');
        await env.cachesImpl.open('kinevo-shell-v1');

        installShellCaching(env, { cacheName: 'kinevo-shell-v1', precacheEntries: [] });

        const pending: Promise<unknown>[] = [];
        const activateEvent = {
            waitUntil: (p: Promise<unknown>) => {
                pending.push(p);
                return p;
            },
        };
        const activateHandler = env.listeners.activate as (e: typeof activateEvent) => void;
        activateHandler(activateEvent);
        await Promise.all(pending);

        expect(env.clients.claim).toHaveBeenCalled();
        const keys = await env.cachesImpl.keys();
        expect(keys).toEqual(['kinevo-shell-v1']);
    });

    it('intercepts navigation requests network-first with cache fallback', async () => {
        const env = makeEnv();
        installShellCaching(env, { cacheName: 'kinevo-shell-v1', precacheEntries: [] });

        const navRequest = makeRequest('https://app.example.com/', { forceNavigate: true });
        (env.fetch as ReturnType<typeof vi.fn>).mockRejectedValue(new Error('offline'));
        const cache = await env.cachesImpl.open('kinevo-shell-v1');
        await cache.put(navRequest, new Response('cached shell', { status: 200 }));

        const fetchHandler = env.listeners.fetch as (e: unknown) => void;
        let respondPromise: Promise<Response> | undefined;
        fetchHandler({
            request: navRequest,
            respondWith: (p: Promise<Response>) => {
                respondPromise = p;
            },
        });

        expect(respondPromise).toBeDefined();
        const response = await respondPromise!;
        expect(await response.text()).toBe('cached shell');
    });

    it('passes business API requests through untouched (never a business engine)', () => {
        const env = makeEnv();
        installShellCaching(env, { cacheName: 'kinevo-shell-v1', precacheEntries: [] });

        const apiRequest = makeRequest('https://app.example.com/api/v1/tasks');
        let responded = false;
        const fetchHandler = env.listeners.fetch as (e: unknown) => void;
        fetchHandler({
            request: apiRequest,
            respondWith: () => {
                responded = true;
            },
        });

        // respondWith must NOT be called for API/business requests.
        expect(responded).toBe(false);
    });
});
