import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', () => ({
    analyticsApi: {
        workLife: vi.fn(),
        overview: vi.fn(),
        heatmap: vi.fn(),
    },
}));

import AnalyticsView from '../AnalyticsView.vue';
import { analyticsApi } from '../api';
import type { AnalyticsOverviewResponse } from '../types';

const mockedOverview = vi.mocked(analyticsApi.overview);
const mockedHeatmap = vi.mocked(analyticsApi.heatmap);

function overview(overrides: Partial<AnalyticsOverviewResponse> = {}): AnalyticsOverviewResponse {
    return {
        from: '2026-08-17',
        to: '2026-08-20',
        work_life: {
            from: '2026-08-17',
            to: '2026-08-20',
            productive_minutes: 75,
            recharge_minutes: 15,
            total_minutes: 90,
            work_ratio: 75 / 90,
            recharge_ratio: 15 / 90,
            band: 'balanced',
            days: [
                {
                    date: '2026-08-18',
                    productive_minutes: 50,
                    recharge_minutes: 0,
                    work_ratio: 1,
                    recharge_ratio: 0,
                    band: 'work_leaning',
                },
                {
                    date: '2026-08-19',
                    productive_minutes: 25,
                    recharge_minutes: 15,
                    work_ratio: 25 / 40,
                    recharge_ratio: 15 / 40,
                    band: 'balanced',
                },
            ],
            disclaimer: 'Time-balance indicator. Not a health diagnosis.',
            previous: {
                from: '2026-08-10',
                to: '2026-08-13',
                productive_minutes: 100,
                recharge_minutes: 0,
                work_ratio: 1,
                recharge_ratio: 0,
            },
            trend: [
                { week_start: '2026-08-17', productive_minutes: 75, recharge_minutes: 15, work_ratio: 75 / 90, recharge_ratio: 15 / 90 },
            ],
            exceptions: [
                { date: '2026-08-18', kind: 'work_only', description: 'Tracked focus time with no recharge time.' },
            ],
        },
        task_completion: {
            from: '2026-08-17',
            to: '2026-08-20',
            total_tasks: 3,
            completed_tasks: 2,
            completion_rate: 2 / 3,
            completed_in_period: 1,
            by_status: { completed: 2, scheduled: 1 },
        },
        goal_progress: {
            total_goals: 2,
            completed_goals: 1,
            completion_rate: 0.5,
            total_milestones: 1,
            completed_milestones: 1,
            goals: [
                {
                    id: 1,
                    title: 'Skripsi',
                    status: 'active',
                    progress: 40,
                    milestones_total: 2,
                    milestones_completed: 1,
                    tasks_total: 4,
                    tasks_completed: 2,
                    days_remaining: 12,
                    deadline_health: 'at_risk',
                },
                {
                    id: 2,
                    title: 'Done goal',
                    status: 'completed',
                    progress: 100,
                    milestones_total: 1,
                    milestones_completed: 1,
                    tasks_total: 2,
                    tasks_completed: 2,
                    days_remaining: null,
                    deadline_health: 'completed',
                },
            ],
            programs: [
                {
                    id: 1,
                    name: 'KRS',
                    status: 'active',
                    tasks_total: 4,
                    tasks_completed: 3,
                    workload_completion: 0.75,
                },
            ],
            deadline_health: { completed: 1, on_track: 0, at_risk: 1, overdue: 0, no_deadline: 0 },
            goal_tasks_total: 6,
            goal_tasks_completed: 4,
            workload_completion: 4 / 6,
        },
        capacity: {
            from: '2026-08-17',
            to: '2026-08-20',
            weeks: [
                {
                    week_start: '2026-08-03',
                    planned_minutes: 500,
                    completed_minutes: 400,
                    realization: 0.8,
                    tag: 'normal',
                },
            ],
            days: [
                {
                    date: '2026-08-18',
                    scheduled_minutes: 60,
                    available_minutes: 240,
                    overload_minutes: 0,
                    status: 'ok',
                },
                {
                    date: '2026-08-19',
                    scheduled_minutes: 300,
                    available_minutes: 120,
                    overload_minutes: 180,
                    status: 'overload',
                },
            ],
            realization_ratio: 0.7,
            average_realization: 0.8,
            confidence: 'MEDIUM',
            recommendation: 'BOOST_AVAILABLE',
            reason: 'Average realization 80% exceeds 90% with no burnout signal; Boost/backlog fill available.',
            target_capacity_minutes: 1440,
        },
        activity: {
            from: '2026-08-17',
            to: '2026-08-20',
            total_events: 2,
            by_type: { task_completed: 1, task_started: 1 },
            recent: [],
        },
        focus: {
            from: '2026-08-17',
            to: '2026-08-20',
            total_sessions: 2,
            total_minutes: 75,
            days: [],
        },
        progress_events: {
            from: '2026-08-17',
            to: '2026-08-20',
            total_events: 1,
            by_type: { milestone_completed: 1 },
            recent: [],
        },
        pillars: {
            from: '2026-08-17',
            to: '2026-08-20',
            pillars: [
                { key: 'karier', label: 'Karier', realization_minutes: 300, target_minutes: 600, percent: 0.5 },
                { key: 'kesehatan', label: 'Kesehatan', realization_minutes: 60, target_minutes: 0, percent: null },
                { key: 'bahasa', label: 'Bahasa', realization_minutes: 0, target_minutes: 240, percent: 0 },
                { key: 'branding', label: 'Branding', realization_minutes: 0, target_minutes: 0, percent: null },
                { key: 'uncategorized', label: 'Uncategorized', realization_minutes: 30, target_minutes: 0, percent: null },
            ],
        },
        ...overrides,
    };
}

describe('AnalyticsView', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-20T12:00:00'));
        mockedOverview.mockReset();
        mockedOverview.mockResolvedValue(overview());
        mockedHeatmap.mockReset();
        mockedHeatmap.mockResolvedValue({
            from: '2026-02-20',
            to: '2026-08-20',
            pillar: null,
            days: [
                { date: '2026-08-18', productive_minutes: 50, recharge_minutes: 15, completion_count: 1, progress_events: 1, intensity: 2 },
                { date: '2026-08-19', productive_minutes: 0, recharge_minutes: 0, completion_count: 0, progress_events: 0, intensity: 0 },
            ],
            legend: [
                { level: 0, label: 'None', description: 'No tracked activity' },
                { level: 1, label: 'Low', description: 'A little activity' },
                { level: 2, label: 'Medium', description: 'Moderate activity' },
                { level: 3, label: 'High', description: 'High activity' },
                { level: 4, label: 'Very high', description: 'Very high activity' },
            ],
        });
    });

    it('loads the default 7-day range on mount', async () => {
        mount(AnalyticsView);
        await flushPromises();

        expect(mockedOverview).toHaveBeenCalledWith('2026-08-14', '2026-08-20');
    });

    it('renders the ratio summary and disclaimer', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        expect(wrapper.get('[data-testid="analytics-ratio"]').text()).toBe('Work 83% · Recharge 17%');
        expect(wrapper.get('[data-testid="analytics-band"]').text()).toBe('Balanced');
        expect(wrapper.get('[data-testid="analytics-disclaimer"]').text()).toBe(
            'Time-balance indicator. Not a health diagnosis.',
        );
        expect(wrapper.findAll('[data-testid="analytics-day"]')).toHaveLength(2);
        expect(wrapper.get('[data-testid="analytics-period-comparison"]').text()).toContain('Work 100% · Recharge 0%');
        expect(wrapper.findAll('[data-testid="analytics-worklife-trend"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="analytics-worklife-exception"]')).toHaveLength(1);
    });

    it('renders the goal progress section', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        const goals = wrapper.findAll('[data-testid="analytics-goal"]');
        expect(goals).toHaveLength(2);
        expect(goals[0].text()).toContain('Skripsi');
        expect(wrapper.get('[data-testid="analytics-goal-deadline"]').text()).toBe('At risk · 12d left');
        expect(wrapper.get('[data-testid="analytics-goal-health"]').text()).toBe('At risk 1 · Completed 1');
        expect(wrapper.get('[data-testid="analytics-program"]').text()).toContain('KRS');
        expect(wrapper.get('[data-testid="analytics-program"]').text()).toContain('75%');
    });

    it('renders the capacity section with overloaded days and trend', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        const days = wrapper.findAll('[data-testid="analytics-capacity-day"]');
        expect(days).toHaveLength(2);
        expect(wrapper.get('[data-testid="analytics-capacity-summary"]').text()).toContain('70% realized');
        expect(wrapper.get('[data-testid="analytics-capacity-summary"]').text()).toContain('1 overloaded days');
        expect(wrapper.get('[data-testid="analytics-capacity-day"]').classes()).not.toContain('bg-red-500');
        expect(days[1].find('[data-testid="analytics-capacity-load"]').classes()).toContain('bg-red-500');
        expect(wrapper.findAll('[data-testid="analytics-capacity-week"]')).toHaveLength(1);
        expect(wrapper.get('[data-testid="analytics-capacity-reason"]').text()).toContain('Boost');
    });

    it('renders the four-pillar realization section', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        const pillars = wrapper.findAll('[data-testid="analytics-pillar"]');
        expect(pillars).toHaveLength(5);
        expect(pillars[0].text()).toContain('Karier');
        expect(pillars[0].get('[data-testid="analytics-pillar-percent"]').text()).toBe('50%');
        expect(pillars[0].text()).toContain('5h completed');
        expect(pillars[0].text()).toContain('10h target');
        expect(pillars[1].get('[data-testid="analytics-pillar-percent"]').text()).toBe('N/A');
        expect(pillars[1].text()).toContain('no target');
    });

    it('switches period presets', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        const buttons = wrapper.findAll('[data-testid="analytics-preset"]');
        expect(buttons).toHaveLength(4);

        await buttons[3].trigger('click');
        await flushPromises();

        expect(mockedOverview).toHaveBeenLastCalledWith('2026-08-01', '2026-08-20');
    });

    it('shows an empty state without tracked time', async () => {
        const base = overview();
        base.work_life = {
            ...base.work_life,
            productive_minutes: 0,
            recharge_minutes: 0,
            total_minutes: 0,
            work_ratio: 0,
            recharge_ratio: 0,
            band: 'no_data',
            days: [],
        };
        mockedOverview.mockResolvedValue(base);

        const wrapper = mount(AnalyticsView);
        await flushPromises();

        expect(wrapper.find('[data-testid="analytics-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="analytics-summary"]').exists()).toBe(false);
    });

    it('renders the error state on failure', async () => {
        mockedOverview.mockRejectedValue({ message: 'Server error' });

        const wrapper = mount(AnalyticsView);
        await flushPromises();

        expect(wrapper.get('[data-testid="analytics-error"]').text()).toBe('Server error');
    });

    it('renders the activity heatmap with legend and accessible list', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        expect(mockedHeatmap).toHaveBeenCalled();
        expect(wrapper.find('[data-testid="analytics-heatmap-grid"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="analytics-heatmap-cell"]').length).toBeGreaterThan(0);
        expect(wrapper.findAll('[data-testid="analytics-heatmap-cell"]').some((c) => c.attributes('aria-label')?.includes('2026-08-18'))).toBe(true);
        expect(wrapper.get('[data-testid="analytics-heatmap-legend"]').text()).toContain('Medium');

        await wrapper.get('[data-testid="analytics-heatmap-list-toggle"]').trigger('click');
        expect(wrapper.findAll('[data-testid="analytics-heatmap-list"] li').length).toBe(2);
    });

    it('filters the heatmap by pillar', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        const select = wrapper.get('[data-testid="analytics-heatmap-pillar"]');
        await select.setValue('karier');
        await flushPromises();

        expect(mockedHeatmap).toHaveBeenLastCalledWith(
            expect.stringMatching(/^2026-/),
            expect.stringMatching(/^2026-/),
            'karier',
        );
    });
});