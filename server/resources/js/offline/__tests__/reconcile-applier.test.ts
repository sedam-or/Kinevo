import { describe, expect, it, vi, beforeEach } from 'vitest';
import { ReconcileMutationApplier } from '../reconcile-applier';
import { submitOfflineAware } from '../reconcile-submit';
import { ApiErrorClass } from '../../api/client';
import type { MutationEnvelope } from '../queue-types';
import type { MutationQueue } from '../queue';

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
        operationType: 'task:update',
        payload: { title: 'Do it', base_version: 3 },
        clientTimestamp: '2026-08-31T00:00:00Z',
        status: 'queued',
        attemptCount: 0,
        ...overrides,
    };
}

describe('ReconcileMutationApplier (ADR-017)', () => {
    beforeEach(() => {
        vi.mocked(apiClient.request).mockReset();
    });

    it('posts the envelope to /sync/reconcile and maps applied', async () => {
        vi.mocked(apiClient.request).mockResolvedValue({
            outcomes: [{ operation_id: 'op-1', status: 'applied' }],
            needs_review: [],
        });

        const outcome = await new ReconcileMutationApplier().apply(envelope());

        expect(outcome).toBe('applied');
        const [path, init] = vi.mocked(apiClient.request).mock.calls[0];
        expect(path).toBe('/sync/reconcile');
        const body = JSON.parse((init as { body: string }).body as string);
        expect(body.operations[0].operation_type).toBe('task:update');
        expect(body.operations[0].operation_id).toBe('op-1');
        expect(body.operations[0].protocol_version).toBe(1);
    });

    it('maps conflict to the conflict outcome and preserves local intent', async () => {
        vi.mocked(apiClient.request).mockResolvedValue({
            outcomes: [{ operation_id: 'op-1', status: 'conflict', code: 'VERSION_CONFLICT', error: 'Task version conflict' }],
            needs_review: ['op-1'],
        });

        const env = envelope();
        const outcome = await new ReconcileMutationApplier().apply(env);

        expect(outcome).toBe('conflict');
        expect(env.lastError).toContain('VERSION_CONFLICT');
    });

    it('maps rejected to permanent (never endless-retry)', async () => {
        vi.mocked(apiClient.request).mockResolvedValue({
            outcomes: [{ operation_id: 'op-1', status: 'rejected', code: 'NOT_FOUND' }],
            needs_review: [],
        });

        const outcome = await new ReconcileMutationApplier().apply(envelope());

        expect(outcome).toBe('permanent');
    });

    it('maps network/5xx to retryable', async () => {
        vi.mocked(apiClient.request).mockRejectedValue(new ApiErrorClass({ status: 503, message: 'unavailable', retryable: true, code: 'UNKNOWN' }));

        const outcome = await new ReconcileMutationApplier().apply(envelope());

        expect(outcome).toBe('retryable');
    });
});

describe('submitOfflineAware (ADR-017 §2.14)', () => {
    const fakeQueue = {
        enqueue: vi.fn(),
    } as unknown as MutationQueue;

    beforeEach(() => {
        vi.clearAllMocks();
        vi.stubGlobal('navigator', { onLine: true });
    });

    it('offline enqueues durably and reports queued', async () => {
        vi.stubGlobal('navigator', { onLine: false });
        const online = vi.fn();

        const result = await submitOfflineAware(fakeQueue, {
            entityType: 'task',
            operationType: 'task:create',
            entityId: null,
            payload: { title: 'X' },
        }, online);

        expect(result.queued).toBe(true);
        expect(fakeQueue.enqueue).toHaveBeenCalledOnce();
        expect(online).not.toHaveBeenCalled();
    });

    it('online runs the request and returns the value', async () => {
        const online = vi.fn().mockResolvedValue({ task: { id: 1 } });

        const result = await submitOfflineAware(fakeQueue, {
            entityType: 'task',
            operationType: 'task:create',
            entityId: null,
            payload: { title: 'X' },
        }, online);

        expect(result.queued).toBe(false);
        expect(online).toHaveBeenCalledWith(expect.any(String));
        expect(result.value).toEqual({ task: { id: 1 } });
        expect(fakeQueue.enqueue).not.toHaveBeenCalled();
    });

    it('network failure mid-flight enqueues the SAME operation_id (safe replay)', async () => {
        const online = vi.fn().mockRejectedValue(new ApiErrorClass({ status: 0, message: 'offline', retryable: true, code: 'NETWORK' }));

        const result = await submitOfflineAware(fakeQueue, {
            entityType: 'task',
            operationType: 'task:create',
            entityId: null,
            payload: { title: 'X' },
        }, online);

        expect(result.queued).toBe(true);
        expect(fakeQueue.enqueue).toHaveBeenCalledOnce();
    });

    it('rejects (4xx) propagate and are not queued', async () => {
        const online = vi.fn().mockRejectedValue(new ApiErrorClass({ status: 422, message: 'invalid', retryable: false, code: 'VALIDATION' }));

        await expect(submitOfflineAware(fakeQueue, {
            entityType: 'task',
            operationType: 'task:create',
            entityId: null,
            payload: { title: '' },
        }, online)).rejects.toBeInstanceOf(ApiErrorClass);
        expect(fakeQueue.enqueue).not.toHaveBeenCalled();
    });
});