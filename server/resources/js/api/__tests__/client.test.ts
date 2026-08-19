import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiClient, ApiErrorClass, isNetworkFailure } from '../client';
import { codeForStatus } from '../types';

const originalFetch = globalThis.fetch;

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

beforeEach(() => {
    localStorage.clear();
});

afterEach(() => {
    globalThis.fetch = originalFetch;
});

describe('codeForStatus', () => {
    it('maps HTTP statuses to canonical error codes', () => {
        expect(codeForStatus(401)).toBe('UNAUTHORIZED');
        expect(codeForStatus(403)).toBe('FORBIDDEN');
        expect(codeForStatus(404)).toBe('NOT_FOUND');
        expect(codeForStatus(409)).toBe('CONFLICT');
        expect(codeForStatus(422)).toBe('VALIDATION');
        expect(codeForStatus(429)).toBe('TOO_MANY_REQUESTS');
        expect(codeForStatus(503)).toBe('UNAVAILABLE');
        expect(codeForStatus(500)).toBe('SERVER');
        expect(codeForStatus(418)).toBe('UNKNOWN');
    });
});

describe('ApiClient', () => {
    it('GETs JSON with the base url and bearer token', async () => {
        localStorage.setItem('kinevo.auth.token', 'tok');
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ ok: true }));
        globalThis.fetch = fetchMock;

        const client = new ApiClient({ isOnline: () => true });
        const result = await client.request<{ ok: boolean }>('/things');

        expect(result.ok).toBe(true);
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/api/v1/things');
        const headers = init.headers as Record<string, string>;
        expect(headers.Authorization).toBe('Bearer tok');
    });

    it('maps 422 to a VALIDATION error with field errors', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue(
            jsonResponse({ errors: { title: ['required'] } }, 422),
        );
        const client = new ApiClient({ isOnline: () => true });

        await expect(client.request('/things')).rejects.toMatchObject({
            code: 'VALIDATION',
            status: 422,
            errors: { title: ['required'] },
        });
    });

    it('maps 401 to UNAUTHORIZED and calls onUnauthorized', async () => {
        const onUnauthorized = vi.fn();
        globalThis.fetch = vi.fn().mockResolvedValue(
            jsonResponse({ error: 'Unauthenticated.' }, 401),
        );
        const client = new ApiClient({ isOnline: () => true, onUnauthorized });

        await expect(client.request('/things')).rejects.toMatchObject({ code: 'UNAUTHORIZED' });
        expect(onUnauthorized).toHaveBeenCalledTimes(1);
    });

    it('maps 409 to CONFLICT and preserves the server code', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue(
            jsonResponse({ code: 'SCHEDULE_VERSION_CONFLICT' }, 409),
        );
        const client = new ApiClient({ isOnline: () => true });

        await expect(client.request('/things')).rejects.toMatchObject({
            code: 'CONFLICT',
            status: 409,
            serverCode: 'SCHEDULE_VERSION_CONFLICT',
        });
    });

    it('throws OFFLINE when the client reports being offline', async () => {
        const client = new ApiClient({ isOnline: () => false });

        await expect(client.request('/things')).rejects.toMatchObject({ code: 'OFFLINE' });
    });

    it('throws NETWORK on fetch TypeError and retries before giving up', async () => {
        const fetchMock = vi
            .fn()
            .mockRejectedValueOnce(new TypeError('Failed to fetch'))
            .mockResolvedValueOnce(jsonResponse({ ok: true }));
        globalThis.fetch = fetchMock;

        const client = new ApiClient({ isOnline: () => true, maxRetries: 2, retryDelayMs: 0 });
        const result = await client.request<{ ok: boolean }>('/things');

        expect(result.ok).toBe(true);
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('gives up retrying after exhausting attempts for a persistent 503', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ error: 'unavailable' }, 503));
        globalThis.fetch = fetchMock;

        const client = new ApiClient({ isOnline: () => true, maxRetries: 1, retryDelayMs: 0 });

        await expect(client.request('/things')).rejects.toMatchObject({ code: 'UNAVAILABLE' });
        expect(fetchMock).toHaveBeenCalledTimes(2); // 1 + 1 retry
    });

    it('does not retry 4xx errors', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ error: 'not found' }, 404));
        globalThis.fetch = fetchMock;

        const client = new ApiClient({ isOnline: () => true, maxRetries: 3, retryDelayMs: 0 });

        await expect(client.request('/things')).rejects.toMatchObject({ code: 'NOT_FOUND' });
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('returns undefined for 204 responses', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }));
        const client = new ApiClient({ isOnline: () => true });

        await expect(client.request<void>('/things')).resolves.toBeUndefined();
    });
});

describe('isNetworkFailure', () => {
    it('recognizes fetch TypeError messages', () => {
        expect(isNetworkFailure(new TypeError('Failed to fetch'))).toBe(true);
        expect(isNetworkFailure(new TypeError('network error'))).toBe(true);
        expect(isNetworkFailure(new Error('boom'))).toBe(false);
    });
});

describe('ApiErrorClass', () => {
    it('implements ApiError with a message', () => {
        const error = new ApiErrorClass({
            code: 'CONFLICT',
            status: 409,
            message: 'version conflict',
            retryable: false,
        });
        expect(error.message).toBe('version conflict');
        expect(error.name).toBe('ApiError');
        expect(error instanceof Error).toBe(true);
    });
});
