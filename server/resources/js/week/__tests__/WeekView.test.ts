import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

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

import WeekView from '../WeekView.vue';
import { weekApi } from '../api';
import type { WeekResponse } from '../types';

const weekResponse: WeekResponse = {
    start: '2026-08-17',
    end: '2026-08-23',
    schedule_version: 5,
    days: [
        { date: '2026-08-17', weekday: 1, task_count: 2, scheduled_minutes: 90 },
        { date: '2026-08-18', weekday: 2, task_count: 0, scheduled_minutes: 0 },
        { date: '2026-08-19', weekday: 3, task_count: 0, scheduled_minutes: 0 },
        { date: '2026-08-20', weekday: 4, task_count: 0, scheduled_minutes: 0 },
        { date: '2026-08-21', weekday: 5, task_count: 0, scheduled_minutes: 0 },
        { date: '2026-08-22', weekday: 6, task_count: 0, scheduled_minutes: 0 },
        { date: '2026-08-23', weekday: 7, task_count: 0, scheduled_minutes: 0 },
    ],
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('WeekView', () => {
    it('renders the week grid and day columns', async () => {
        vi.mocked(weekApi.week).mockResolvedValue(weekResponse);
        vi.mocked(weekApi.range).mockResolvedValue({ from: 'a', to: 'b', schedule_version: 1, events: [] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(WeekView, { props: { anchorDate: '2026-08-19' }, global: { plugins: [pinia] } });
        await flushPromises();

        expect(weekApi.week).toHaveBeenCalled();
        expect(wrapper.findAll('[data-testid="week-day"]')).toHaveLength(7);
        expect(wrapper.find('[data-testid="week-range"]').text()).toContain('2026-08-17');
    });

    it('shows an overload indicator when a day exceeds capacity', async () => {
        vi.mocked(weekApi.week).mockResolvedValue({
            ...weekResponse,
            days: [{ date: '2026-08-17', weekday: 1, task_count: 9, scheduled_minutes: 900 }],
        });
        vi.mocked(weekApi.range).mockResolvedValue({ from: 'a', to: 'b', schedule_version: 1, events: [] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(WeekView, { props: { anchorDate: '2026-08-19' }, global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="week-overload"]').exists()).toBe(true);
    });
});
