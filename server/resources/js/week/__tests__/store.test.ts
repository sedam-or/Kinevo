import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        weekApi: {
            week: vi.fn(),
            calendar: vi.fn(),
            range: vi.fn(),
        },
    };
});

import { useWeekStore } from '../store';
import { weekApi } from '../api';
import type { CalendarResponse, WeekResponse } from '../types';

const weekResponse: WeekResponse = {
    start: '2026-08-17',
    end: '2026-08-23',
    schedule_version: 5,
    days: [
        { date: '2026-08-17', weekday: 1, task_count: 2, scheduled_minutes: 90 },
        { date: '2026-08-18', weekday: 2, task_count: 1, scheduled_minutes: 60 },
        { date: '2026-08-19', weekday: 3, task_count: 0, scheduled_minutes: 0 },
        { date: '2026-08-20', weekday: 4, task_count: 0, scheduled_minutes: 0 },
        { date: '2026-08-21', weekday: 5, task_count: 0, scheduled_minutes: 0 },
        { date: '2026-08-22', weekday: 6, task_count: 0, scheduled_minutes: 0 },
        { date: '2026-08-23', weekday: 7, task_count: 0, scheduled_minutes: 0 },
    ],
};

const calendarResponse: CalendarResponse = {
    year: 2026,
    month: 8,
    schedule_version: 5,
    days: [{ date: '2026-08-19', day: 19, task_count: 1, scheduled_minutes: 60 }],
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('week store', () => {
    it('loads a week summary', async () => {
        vi.mocked(weekApi.week).mockResolvedValue(weekResponse);

        const store = useWeekStore();
        await store.loadWeek('2026-08-17');

        expect(store.weekStart).toBe('2026-08-17');
        expect(store.weekDays).toHaveLength(7);
        expect(store.weekDays[0].scheduled_minutes).toBe(90);
    });

    it('loads a calendar month', async () => {
        vi.mocked(weekApi.calendar).mockResolvedValue(calendarResponse);

        const store = useWeekStore();
        await store.loadCalendar('2026-08');

        expect(store.calendarYear).toBe(2026);
        expect(store.calendarMonth).toBe(8);
        expect(store.calendarDays).toHaveLength(1);
    });

    it('records an error when loading fails', async () => {
        vi.mocked(weekApi.week).mockRejectedValue({ code: 'UNAUTHORIZED', status: 401, message: 'No', retryable: false });

        const store = useWeekStore();
        await store.loadWeek('2026-08-17');

        expect(store.error?.code).toBe('UNAUTHORIZED');
        expect(store.weekDays).toHaveLength(0);
    });

    it('loads a schedule range for deadline/assignment detail', async () => {
        vi.mocked(weekApi.range).mockResolvedValue({ from: 'a', to: 'b', schedule_version: 1, events: [] });

        const store = useWeekStore();
        await store.loadRange('2026-08-17', '2026-08-23');

        expect(store.rangeEvents).toHaveLength(0);
    });
});
