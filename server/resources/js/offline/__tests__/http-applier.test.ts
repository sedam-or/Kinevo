import { describe, expect, it, vi, beforeEach } from 'vitest';
import { HttpMutationApplier, outcomeForError } from '../http-applier';
import { ApiErrorClass } from '../../api/client';
import type { MutationEnvelope } from '../queue-types';

vi.mock('../../api/client', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../../api/client')>();
    return {
        ...actual,
        apiClient: {
            request: vi.fn(),
        },
    };
});

import { apiClient } from '../../api/client';

function envelope(overrides: Partial<MutationEnvelope> = {}): MutationEnvelope {
    return {
        operationId: 'op-1',
        entityType: 'task',
        entityId: 7,
        operationType: 'update',
        payload: { title: 'Do it' },
        clientTimestamp: '2026-08-19T00:00:00Z',
        status: 'queued',
        attemptCount: 0,
        ...overrides,
    };
}

describe('outcomeForError', () => {
    it('maps 409 to conflict', () => {
        expect(outcomeForError(new ApiErrorClass({ status: 409, code: 'CONFLICT', message: 'c', retryable: false }))).toBe('conflict');
    });

    it('maps offline/network and transient 5xx/429 to retryable', () => {
        expect(outcomeForError(new ApiErrorClass({ status: 0, code: 'OFFLINE', message: 'o', retryable: true }))).toBe('retryable');
        expect(outcomeForError(new ApiErrorClass({ status: 429, code: 'TOO_MANY_REQUESTS', message: 't', retryable: true }))).toBe('retryable');
        expect(outcomeForError(new ApiErrorClass({ status: 503, code: 'UNAVAILABLE', message: 's', retryable: true }))).toBe('retryable');
    });

    it('maps other 4xx to permanent', () => {
        expect(outcomeForError(new ApiErrorClass({ status: 422, code: 'VALIDATION', message: 'v', retryable: false }))).toBe('permanent');
        expect(outcomeForError(new ApiErrorClass({ status: 401, code: 'UNAUTHORIZED', message: 'u', retryable: false }))).toBe('permanent');
    });

    it('maps raw network failures to retryable', () => {
        expect(outcomeForError(new TypeError('Failed to fetch'))).toBe('retryable');
    });

    it('maps unknown errors to permanent', () => {
        expect(outcomeForError(new Error('boom'))).toBe('permanent');
    });
});

describe('HttpMutationApplier', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(apiClient.request).mockResolvedValue({});
    });

    it('applies a known task update to the right endpoint', async () => {
        const applier = new HttpMutationApplier();
        const outcome = await applier.apply(envelope());

        expect(outcome).toBe('applied');
        expect(apiClient.request).toHaveBeenCalledWith('/tasks/7', {
            method: 'PUT',
            body: JSON.stringify({ title: 'Do it' }),
            noRetry: true,
        });
    });

    it('routes each supported operation to its endpoint', async () => {
        const applier = new HttpMutationApplier();
        const cases: Array<[Partial<MutationEnvelope>, string, string]> = [
            [{ entityType: 'task', operationType: 'create' }, '/tasks', 'POST'],
            [{ entityType: 'task', operationType: 'status' }, '/tasks/7/status', 'POST'],
            [{ entityType: 'note', operationType: 'create' }, '/notes', 'POST'],
            [{ entityType: 'note', operationType: 'update' }, '/notes/7', 'PATCH'],
            [{ entityType: 'quick_capture', operationType: 'create' }, '/quick-capture', 'POST'],
            [{ entityType: 'canvas', operationType: 'update' }, '/canvases/7', 'PUT'],
        ];

        for (const [overrides, path, method] of cases) {
            const outcome = await applier.apply(envelope(overrides));
            expect(outcome).toBe('applied');
            expect(apiClient.request).toHaveBeenCalledWith(
                path,
                expect.objectContaining({ method }),
            );
        }
    });

    it('preserves local data (permanent failure) for unsupported operations', async () => {
        const applier = new HttpMutationApplier();
        const outcome = await applier.apply(envelope({ entityType: 'unknown', operationType: 'nope' }));
        expect(outcome).toBe('permanent');
        expect(apiClient.request).not.toHaveBeenCalled();
    });

    it('surfaces 409 as conflict', async () => {
        vi.mocked(apiClient.request).mockRejectedValue(
            new ApiErrorClass({ status: 409, code: 'CONFLICT', message: 'c', retryable: false }),
        );
        const applier = new HttpMutationApplier();
        expect(await applier.apply(envelope())).toBe('conflict');
    });

    it('surfaces network failure as retryable', async () => {
        vi.mocked(apiClient.request).mockRejectedValue(new TypeError('Failed to fetch'));
        const applier = new HttpMutationApplier();
        expect(await applier.apply(envelope())).toBe('retryable');
    });

    it('surfaces other 4xx as permanent', async () => {
        vi.mocked(apiClient.request).mockRejectedValue(
            new ApiErrorClass({ status: 422, code: 'VALIDATION', message: 'v', retryable: false }),
        );
        const applier = new HttpMutationApplier();
        expect(await applier.apply(envelope())).toBe('permanent');
    });
});