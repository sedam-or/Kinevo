import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        rechargeApi: {
            status: vi.fn(),
            list: vi.fn(),
            start: vi.fn(),
            pause: vi.fn(),
            resume: vi.fn(),
            complete: vi.fn(),
            abandon: vi.fn(),
        },
    };
});

import { useRechargeStore } from '../store';
import { rechargeApi } from '../api';
import type { RechargeSession, RechargeStatusResponse } from '../types';

const running: RechargeSession = {
    id: 1,
    user_id: 1,
    status: 'running',
    started_at: '2026-08-20T09:00:00',
    last_resumed_at: '2026-08-20T09:00:00',
    accumulated_seconds: 0,
    elapsed_seconds: 0,
    duration_minutes: null,
    ended_at: null,
};

function status(overrides: Partial<RechargeStatusResponse> = {}): RechargeStatusResponse {
    return {
        recharge: null,
        cue_available: false,
        completed_focus_today: 0,
        due_recharges: 0,
        completed_recharges_today: 0,
        recharge_minutes_today: 0,
        productive_minutes_today: 0,
        work_ratio: 0,
        recharge_ratio: 0,
        ...overrides,
    };
}

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    vi.useRealTimers();
});

describe('recharge store', () => {
    it('loads status and exposes the recharge cue', async () => {
        vi.mocked(rechargeApi.status).mockResolvedValue(status({ cue_available: true, completed_focus_today: 2, due_recharges: 1 }));

        const store = useRechargeStore();
        await store.loadStatus('2026-08-20');

        expect(rechargeApi.status).toHaveBeenCalledWith('2026-08-20');
        expect(store.cueAvailable).toBe(true);
        expect(store.completedFocusToday).toBe(2);
        expect(store.dueRecharges).toBe(1);
        expect(store.hasActive).toBe(false);
    });

    it('tracks the active recharge session and elapsed time', async () => {
        vi.mocked(rechargeApi.status).mockResolvedValue(status({ recharge: running }));

        const store = useRechargeStore();
        await store.loadStatus();

        expect(store.session?.status).toBe('running');
        expect(store.isRunning).toBe(true);
        expect(store.hasActive).toBe(true);
    });

    it('starts a recharge timer and refreshes the status', async () => {
        vi.mocked(rechargeApi.start).mockResolvedValue({ recharge: running });
        vi.mocked(rechargeApi.status).mockResolvedValue(status({ recharge: running, cue_available: true }));

        const store = useRechargeStore();
        await store.start();

        expect(rechargeApi.start).toHaveBeenCalled();
        expect(store.session?.id).toBe(1);
        expect(store.isRunning).toBe(true);
    });

    it('pauses the timer and banks elapsed time', async () => {
        vi.mocked(rechargeApi.pause).mockResolvedValue({
            recharge: { ...running, status: 'paused', last_resumed_at: null, accumulated_seconds: 300 },
        });

        const store = useRechargeStore();
        store.session = { ...running };
        await store.pause();

        expect(store.isPaused).toBe(true);
        expect(store.elapsedSeconds).toBe(300);
    });

    it('resumes a paused timer', async () => {
        vi.mocked(rechargeApi.resume).mockResolvedValue({ recharge: running });

        const store = useRechargeStore();
        store.session = { ...running, status: 'paused', accumulated_seconds: 300 };
        await store.resume();

        expect(store.isRunning).toBe(true);
    });

    it('completes the timer and records the tracked duration', async () => {
        vi.mocked(rechargeApi.complete).mockResolvedValue({
            recharge: { ...running, status: 'completed', accumulated_seconds: 900, duration_minutes: 15, ended_at: '2026-08-20T09:15:00' },
        });

        const store = useRechargeStore();
        store.session = { ...running };
        await store.complete();

        expect(store.session?.status).toBe('completed');
        expect(store.session?.duration_minutes).toBe(15);
        expect(store.isRunning).toBe(false);
        expect(store.isPaused).toBe(false);
    });

    it('abandons the timer without a recorded duration', async () => {
        vi.mocked(rechargeApi.abandon).mockResolvedValue({
            recharge: { ...running, status: 'abandoned', accumulated_seconds: 120, ended_at: '2026-08-20T09:02:00' },
        });

        const store = useRechargeStore();
        store.session = { ...running };
        await store.abandon();

        expect(store.session?.status).toBe('abandoned');
        expect(store.session?.duration_minutes).toBeNull();
    });

    it('records an error when the status load fails', async () => {
        vi.mocked(rechargeApi.status).mockRejectedValue({ code: 'SERVER', status: 500, message: 'No', retryable: true });

        const store = useRechargeStore();
        await store.loadStatus();

        expect(store.error?.code).toBe('SERVER');
    });
});