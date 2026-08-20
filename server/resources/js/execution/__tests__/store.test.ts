import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        executionApi: {
            active: vi.fn(),
            list: vi.fn(),
            start: vi.fn(),
            pause: vi.fn(),
            resume: vi.fn(),
            complete: vi.fn(),
            abandon: vi.fn(),
        },
    };
});

import { useExecutionStore } from '../store';
import { executionApi } from '../api';
import type { ExecutionSession } from '../types';

const running: ExecutionSession = {
    id: 1,
    user_id: 1,
    task_id: 10,
    status: 'running',
    started_at: '2026-08-19T09:00:00',
    last_resumed_at: '2026-08-19T09:00:00',
    accumulated_seconds: 0,
    elapsed_seconds: 0,
    ended_at: null,
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    vi.useRealTimers();
});

describe('execution store', () => {
    it('loads the active session and tracks elapsed while running', async () => {
        vi.mocked(executionApi.active).mockResolvedValue({ execution: running });

        const store = useExecutionStore();
        await store.loadActive();

        expect(store.session?.status).toBe('running');
        expect(store.isRunning).toBe(true);
        expect(store.hasActive).toBe(true);
    });

    it('starts a timer for a task', async () => {
        const started = { ...running, id: 2 };
        vi.mocked(executionApi.start).mockResolvedValue({ execution: started });

        const store = useExecutionStore();
        await store.start(10);

        expect(executionApi.start).toHaveBeenCalledWith(10);
        expect(store.session?.id).toBe(2);
        expect(store.isRunning).toBe(true);
    });

    it('pauses the timer and keeps the banked elapsed time', async () => {
        vi.mocked(executionApi.pause).mockResolvedValue({
            execution: {
                ...running,
                status: 'paused',
                last_resumed_at: null,
                accumulated_seconds: 300,
            },
        });

        const store = useExecutionStore();
        store.session = { ...running };
        await store.pause();

        expect(store.isPaused).toBe(true);
        expect(store.session?.accumulated_seconds).toBe(300);
        expect(store.elapsedSeconds).toBe(300);
    });

    it('resumes a paused timer', async () => {
        vi.mocked(executionApi.resume).mockResolvedValue({ execution: running });

        const store = useExecutionStore();
        store.session = { ...running, status: 'paused', accumulated_seconds: 300 };
        await store.resume();

        expect(store.isRunning).toBe(true);
    });

    it('completes the timer and leaves no active session', async () => {
        vi.mocked(executionApi.complete).mockResolvedValue({
            execution: { ...running, status: 'completed', accumulated_seconds: 2700, ended_at: '2026-08-19T09:45:00' },
            focus_session: { id: 1, user_id: 1, task_id: 10, started_at: '2026-08-19T09:00:00', ended_at: '2026-08-19T09:45:00', duration_minutes: 45 },
            task: { id: 10, status: 'completed', progress: 100, version: 2, title: 'Write report' },
            continuation: null,
        });

        const store = useExecutionStore();
        store.session = { ...running };
        await store.complete();

        expect(store.isRunning).toBe(false);
        expect(store.isPaused).toBe(false);
        expect(store.session?.status).toBe('completed');
    });

    it('abandons the timer', async () => {
        vi.mocked(executionApi.abandon).mockResolvedValue({
            execution: { ...running, status: 'abandoned', ended_at: '2026-08-19T09:05:00' },
        });

        const store = useExecutionStore();
        store.session = { ...running };
        await store.abandon();

        expect(store.session?.status).toBe('abandoned');
        expect(store.isRunning).toBe(false);
    });

    it('records an error when loading fails', async () => {
        vi.mocked(executionApi.active).mockRejectedValue({ code: 'SERVER', status: 500, message: 'No', retryable: true });

        const store = useExecutionStore();
        await store.loadActive();

        expect(store.error?.code).toBe('SERVER');
    });
});