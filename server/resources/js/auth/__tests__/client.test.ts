import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { authApi } from '../client';
import { clearToken, readToken, writeToken } from '../../api/token';

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
    clearToken();
});

describe('auth token persistence', () => {
    it('writes and reads the token', () => {
        expect(readToken()).toBeNull();
        writeToken('abc123');
        expect(readToken()).toBe('abc123');
    });

    it('clears the token', () => {
        writeToken('abc123');
        clearToken();
        expect(readToken()).toBeNull();
    });
});

describe('authApi', () => {
    it('login posts credentials and returns the token', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            jsonResponse({ user: { id: 1, name: 'A', email: 'a@b.c' }, token: 'tok' }),
        );
        globalThis.fetch = fetchMock;

        const result = await authApi.login({ email: 'a@b.c', password: 'secret' });

        expect(result.token).toBe('tok');
        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/api/v1/auth/login');
        expect((init as RequestInit).method).toBe('POST');
    });

    it('attaches the bearer token to authenticated requests', async () => {
        writeToken('tok123');
        const fetchMock = vi.fn().mockResolvedValue(
            jsonResponse({ user: { id: 1, name: 'A', email: 'a@b.c' } }),
        );
        globalThis.fetch = fetchMock;

        await authApi.me();

        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        const headers = init.headers as Record<string, string>;
        expect(headers.Authorization).toBe('Bearer tok123');
    });

    it('throws a parsed ApiError with field errors on 422', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            jsonResponse({ errors: { email: ['The email field is required.'] } }, 422),
        );
        globalThis.fetch = fetchMock;

        await expect(authApi.login({ email: '', password: '' })).rejects.toMatchObject({
            status: 422,
            errors: { email: ['The email field is required.'] },
        });
    });

    it('throws a parsed ApiError message on 401', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            jsonResponse({ error: 'The provided credentials are incorrect.' }, 401),
        );
        globalThis.fetch = fetchMock;

        await expect(authApi.login({ email: 'a@b.c', password: 'wrong' })).rejects.toMatchObject({
            status: 401,
            message: 'The provided credentials are incorrect.',
        });
    });
});
