import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        todayApi: {
            today: vi.fn(),
            quickCapture: vi.fn(),
        },
    };
});

import TodayView from '../TodayView.vue';
import { todayApi } from '../api';
import { useShellStore } from '../../shell/store';
import type { TodayResponse } from '../types';

const response: TodayResponse = {
    date: '2026-08-19',
    schedule_version: 5,
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
                locked: true,
                version: 1,
            },
            locked: true,
            conflict: false,
            task: { id: 10, user_id: 1, program_id: null, goal_id: null, milestone_id: null, title: 'Write report', description: null, status: 'scheduled', priority_tier: 1, estimated_minutes: 60, due_at: null, progress: 0, version: 1 },
            program: null,
            goal: null,
            milestone: null,
        },
    ],
    empty_slots: [{ start: '2026-08-19T10:00:00', end: '2026-08-19T11:00:00', duration_minutes: 60 }],
    hard_landscape: [{ id: 1, user_id: 1, title: 'Lunch', start: '2026-08-19T12:00:00', end: '2026-08-19T13:00:00' }],
    capacity: { scheduled_minutes: 60, available_minutes: 300, overload_minutes: 0, status: 'ok' },
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('TodayView', () => {
    it('renders the date header and loads today data', async () => {
        vi.mocked(todayApi.today).mockResolvedValue(response);
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(todayApi.today).toHaveBeenCalledWith('2026-08-19');
        expect(wrapper.find('[data-testid="today-date"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="today-timeline"]').exists()).toBe(true);
    });

    it('shows the NOW card with task, lock, and timeline events', async () => {
        vi.mocked(todayApi.today).mockResolvedValue(response);
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="timeline-event"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="timeline-landscape"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="timeline-empty"]').exists()).toBe(true);
    });

    it('renders the quick capture form', async () => {
        vi.mocked(todayApi.today).mockResolvedValue(response);
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="quick-capture"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="qc-title"]').exists()).toBe(true);
    });

    it('displays the shell sync state', async () => {
        vi.mocked(todayApi.today).mockResolvedValue(response);
        const pinia = createPinia();
        setActivePinia(pinia);
        const shell = useShellStore();
        shell.setSyncState('offline');

        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="today-sync"]').text()).toContain('offline');
    });
});
