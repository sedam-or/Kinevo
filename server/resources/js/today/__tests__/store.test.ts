import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        todayApi: {
            today: vi.fn(),
            quickCapture: vi.fn(),
            miniPause: vi.fn(),
            emergencyPause: vi.fn(),
            startBreak: vi.fn(),
            endBreak: vi.fn(),
        },
    };
});

import { useTodayStore } from '../store';
import { todayApi } from '../api';
import type { TodayResponse } from '../types';

const response: TodayResponse = {
    date: '2026-08-19',
    schedule_version: 5,
    pause: null,
    break: null,
    events: [
        {
            assignment: {
                id: 1,
                user_id: 1,
                task_id: 10,
                date: '2026-08-19',
                start_at: '2026-08-19T09:00:00',
                end_at: '2026-08-19T10:00:00',
                duration_minutes: 60,
                status: 'scheduled',
                source: 'manual',
                schedule_version: 5,
                locked: false,
                version: 1,
            },
            locked: false,
            conflict: false,
            task: { id: 10, user_id: 1, program_id: null, goal_id: null, milestone_id: null, title: 'Write report', description: null, status: 'scheduled', priority_tier: 1, estimated_minutes: 60, due_at: null, progress: 0, version: 1 },
            program: null,
            goal: null,
            milestone: null,
        },
    ],
    empty_slots: [{ start: '2026-08-19T10:00:00', end: '2026-08-19T11:00:00', duration_minutes: 60 }],
    hard_landscape: [],
    capacity: { scheduled_minutes: 60, available_minutes: 300, overload_minutes: 0, status: 'ok' },
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('today store', () => {
    it('loads a day and applies the response', async () => {
        vi.mocked(todayApi.today).mockResolvedValue(response);

        const store = useTodayStore();
        await store.load('2026-08-19');

        expect(store.date).toBe('2026-08-19');
        expect(store.events).toHaveLength(1);
        expect(store.emptySlots).toHaveLength(1);
        expect(store.capacity?.status).toBe('ok');
        expect(store.hasData).toBe(true);
    });

    it('records an error when loading fails', async () => {
        vi.mocked(todayApi.today).mockRejectedValue({ code: 'UNAUTHORIZED', status: 401, message: 'No', retryable: false });

        const store = useTodayStore();
        await store.load('2026-08-19');

        expect(store.error?.code).toBe('UNAUTHORIZED');
        expect(store.hasData).toBe(false);
    });

    it('clears state', async () => {
        vi.mocked(todayApi.today).mockResolvedValue(response);
        const store = useTodayStore();
        await store.load('2026-08-19');
        store.clear();

        expect(store.date).toBeNull();
        expect(store.events).toHaveLength(0);
        expect(store.capacity).toBeNull();
    });
});
