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
import { useShellStore } from '../../shell/store';
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

    it('exposes title/metric/period/unit/legend/context metadata on every chart', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        // period reflects the resolved overview range
        const period = wrapper.get('[data-testid="chart-period-summary"]');
        expect(period.text()).toContain('2026-08-17 – 2026-08-20');

        // legend swatches match the bars they describe
        expect(wrapper.get('[data-testid="chart-meta-summary"]').text()).toContain('Work');
        expect(wrapper.get('[data-testid="chart-meta-summary"]').text()).toContain('Recharge');
        expect(wrapper.get('[data-testid="chart-meta-capacity"]').text()).toContain('Scheduled');
        expect(wrapper.get('[data-testid="chart-meta-capacity"]').text()).toContain('Overload');
        expect(wrapper.get('[data-testid="chart-meta-days"]').text()).toContain('minutes per day');

        // unit captions present on each chart
        for (const id of ['summary', 'goals', 'capacity', 'pillars', 'days']) {
            expect(wrapper.get(`[data-testid="chart-unit-${id}"]`).text().length).toBeGreaterThan(0);
        }
    });

    it('follows the shared surface hierarchy — not every section is a card', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        // L2 Primary: decision charts stay boxed, on-token.
        for (const id of ['analytics-summary', 'analytics-goals', 'analytics-capacity']) {
            expect(wrapper.get(`[data-testid="${id}"]`).classes(), id).toContain('surface-primary');
        }
        // L4 Supporting: raw/detail groups are open — hairline, never boxed.
        for (const id of ['analytics-pillars', 'analytics-heatmap', 'analytics-days']) {
            const classes = wrapper.get(`[data-testid="${id}"]`).classes();
            expect(classes, id).toContain('surface-supporting');
            expect(classes.join(' '), `${id} must not be boxed`).not.toContain('rounded-sm');
        }
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

    it('renders a What/Why/What-to-do interpretation for the work-life chart', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        const changed = wrapper.get('[data-testid="interpretation-summary-changed"]');
        expect(changed.text()).toContain('Work share 83% vs 100%');
        expect(wrapper.get('[data-testid="interpretation-summary-why"]').text()).toContain('balanced');
        expect(wrapper.get('[data-testid="interpretation-summary-do"]').text()).toContain('Keep the current rhythm');
    });

    it('leads with the executive signal before any chart', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        // Fixture state: 1 at-risk goal outranks the overloaded capacity day.
        const signal = wrapper.get('[data-testid="analytics-executive-signal"]');
        expect(signal.get('[data-testid="analytics-signal-headline"]').text()).toContain('1 goal at risk');
        expect(signal.get('[data-testid="analytics-signal-action"]').text()).toContain('Review goal');

        // Executive signal first: no chart precedes it…
        const charts = ['analytics-summary', 'analytics-goals', 'analytics-capacity', 'analytics-pillars', 'analytics-heatmap', 'analytics-days'];
        for (const id of charts) {
            const preceding = signal.element.compareDocumentPosition(wrapper.get(`[data-testid="${id}"]`).element);
            expect(preceding & Node.DOCUMENT_POSITION_PRECEDING, `${id} must sit below the executive signal`).toBe(0);
        }
        // …and the page reads signal → charts → raw data, not charts-first.
        for (let i = 1; i < charts.length; i++) {
            const above = wrapper.get(`[data-testid="${charts[i - 1]}"]`).element;
            const below = wrapper.get(`[data-testid="${charts[i]}"]`).element;
            expect(above.compareDocumentPosition(below) & Node.DOCUMENT_POSITION_FOLLOWING, `${charts[i]} must follow ${charts[i - 1]}`).toBeTruthy();
        }
    });

    it('escalates overdue goals to the danger signal', async () => {
        mockedOverview.mockResolvedValue(overview({
            work_life: { ...overview().work_life },
            goal_progress: {
                ...overview().goal_progress,
                deadline_health: { completed: 0, on_track: 0, at_risk: 0, overdue: 2, no_deadline: 0 },
            },
            capacity: {
                ...overview().capacity,
                days: overview().capacity.days.map((d) => ({ ...d, status: 'ok' as const, overload_minutes: 0 })),
            },
        }));
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        const signal = wrapper.get('[data-testid="analytics-executive-signal"]');
        expect(signal.get('[data-testid="analytics-signal-headline"]').text()).toContain('2 goals are overdue');
        expect(signal.classes().join(' ')).toContain('text-danger');
        await signal.get('[data-testid="analytics-signal-action"]').trigger('click');
        expect(useShellStore().activeView).toBe('goals');
    });

    it('routes an overload-only period to Review schedule', async () => {
        const base = overview();
        mockedOverview.mockResolvedValue(overview({
            goal_progress: {
                ...base.goal_progress,
                deadline_health: { completed: 0, on_track: 0, at_risk: 0, overdue: 0, no_deadline: base.goal_progress.deadline_health.no_deadline },
            },
        }));
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        const signal = wrapper.get('[data-testid="analytics-executive-signal"]');
        expect(signal.get('[data-testid="analytics-signal-headline"]').text()).toContain('1 overloaded day');
        await signal.get('[data-testid="analytics-signal-action"]').trigger('click');
        expect(useShellStore().activeView).toBe('schedule');
    });

    it('renders the execution section from task completion data', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        // Fixture: 2/3 complete (67%) — above the low-completion threshold.
        expect(wrapper.get('[data-testid="analytics-execution"]').isVisible()).toBe(true);
        expect(wrapper.get('[data-testid="analytics-execution-rate"]').text()).toContain('67% complete');
        expect(wrapper.get('[data-testid="interpretation-execution-do"]').text()).toContain('Keep completing scheduled tasks');
        expect(wrapper.find('[data-testid="analytics-reduce-workload"]').exists()).toBe(false);
    });

    it('drives reduce-workload when completion is low', async () => {
        const base = overview();
        mockedOverview.mockResolvedValue(overview({
            task_completion: { ...base.task_completion, total_tasks: 5, completed_tasks: 2, completion_rate: 0.4, completed_in_period: 2 },
        }));
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        expect(wrapper.get('[data-testid="analytics-execution-rate"]').classes().join(' ')).toContain('text-danger');
        expect(wrapper.get('[data-testid="analytics-execution-bar"]').classes().join(' ')).toContain('bg-danger');
        await wrapper.get('[data-testid="analytics-reduce-workload"]').trigger('click');
        expect(useShellStore().activeView).toBe('schedule');
    });

    it('drives review-milestone when goals press the deadline', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        // Fixture: 1 at-risk goal.
        const button = wrapper.get('[data-testid="analytics-review-milestone"]');
        expect(button.text()).toContain('Review milestone');
        await button.trigger('click');
        expect(useShellStore().activeView).toBe('goals');
    });

    it('hides review-milestone when no goal presses a deadline', async () => {
        const base = overview();
        mockedOverview.mockResolvedValue(overview({
            goal_progress: {
                ...base.goal_progress,
                deadline_health: { completed: base.goal_progress.deadline_health.completed, on_track: 0, at_risk: 0, overdue: 0, no_deadline: 0 },
            },
        }));
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        expect(wrapper.find('[data-testid="analytics-review-milestone"]').exists()).toBe(false);
    });

    it('drives recovery to Today on work-heavy imbalance', async () => {
        const base = overview();
        mockedOverview.mockResolvedValue(overview({
            work_life: {
                ...base.work_life,
                band: 'work_leaning',
            },
        }));
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        const button = wrapper.get('[data-testid="analytics-recovery-action"]');
        expect(button.text()).toContain('Plan a recharge block');
        await button.trigger('click');
        expect(useShellStore().activeView).toBe('today');
    });

    it('hides the recovery action while balanced', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        // Fixture band is balanced — no imbalance action.
        expect(wrapper.find('[data-testid="analytics-recovery-action"]').exists()).toBe(false);
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
        expect(wrapper.get('[data-testid="interpretation-goals-why"]').text()).toContain('1 goal is at risk');
        expect(wrapper.get('[data-testid="interpretation-goals-do"]').text()).toContain('Review the at-risk goal');
    });

    it('renders the capacity section with overloaded days and trend', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        const days = wrapper.findAll('[data-testid="analytics-capacity-day"]');
        expect(days).toHaveLength(2);
        expect(wrapper.get('[data-testid="analytics-capacity-summary"]').text()).toContain('70% realized');
        expect(wrapper.get('[data-testid="analytics-capacity-summary"]').text()).toContain('1 overloaded days');
        expect(wrapper.get('[data-testid="analytics-capacity-day"]').classes()).not.toContain('bg-danger');
        expect(days[1].find('[data-testid="analytics-capacity-load"]').classes()).toContain('bg-danger');
        expect(wrapper.findAll('[data-testid="analytics-capacity-week"]')).toHaveLength(1);
        expect(wrapper.get('[data-testid="analytics-capacity-reason"]').text()).toContain('Boost');
        expect(wrapper.get('[data-testid="interpretation-capacity-changed"]').text()).toContain('70% realized');
        expect(wrapper.get('[data-testid="interpretation-capacity-do"]').text()).toContain('Spare capacity exists');
        expect(wrapper.get('[data-testid="analytics-review-schedule"]').text()).toContain('Review schedule');
    });

    it('navigates to the schedule workflow from the capacity card', async () => {
        const wrapper = mount(AnalyticsView);
        await flushPromises();

        await wrapper.get('[data-testid="analytics-review-schedule"]').trigger('click');
        await flushPromises();

        const shell = useShellStore();
        expect(shell.activeView).toBe('schedule');
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
        expect(wrapper.get('[data-testid="interpretation-pillars-changed"]').text()).toContain('Bahasa sits at 0%');
        expect(wrapper.get('[data-testid="interpretation-pillars-do"]').text()).toContain('Protect time for Bahasa');
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
        expect(wrapper.get('[data-testid="interpretation-heatmap-changed"]').text()).toContain('focus across 1 active day');
        expect(wrapper.get('[data-testid="interpretation-heatmap-do"]').text()).toContain('Spread focus across more days');
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