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

import CalendarView from '../CalendarView.vue';
import { weekApi } from '../api';

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('CalendarView', () => {
    it('loads a calendar month and renders cells', async () => {
        vi.mocked(weekApi.calendar).mockResolvedValue({
            year: 2026,
            month: 8,
            schedule_version: 1,
            days: [{ date: '2026-08-19', day: 19, task_count: 2, scheduled_minutes: 120 }],
        });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(CalendarView, { props: { anchorDate: '2026-08-19' }, global: { plugins: [pinia] } });
        await flushPromises();

        expect(weekApi.calendar).toHaveBeenCalledWith('2026-08');
        expect(wrapper.find('[data-testid="calendar-grid"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="calendar-month"]').text()).toContain('2026-08');
    });

    it('navigates to the next month', async () => {
        vi.mocked(weekApi.calendar).mockResolvedValue({
            year: 2026,
            month: 8,
            schedule_version: 1,
            days: [],
        });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(CalendarView, { props: { anchorDate: '2026-08-19' }, global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="calendar-next"]').trigger('click');
        await flushPromises();

        expect(weekApi.calendar).toHaveBeenCalledWith('2026-09');
    });
});
