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
            miniPause: vi.fn(),
            emergencyPause: vi.fn(),
            weekRange: vi.fn(),
            startBreak: vi.fn(),
            endBreak: vi.fn(),
            getBoostSetup: vi.fn(),
            setBoostTarget: vi.fn(),
            endBoostTarget: vi.fn(),
        },
    };
});

vi.mock('../../execution/api', () => ({
    executionApi: {
        active: vi.fn().mockResolvedValue({ execution: null }),
        start: vi.fn(),
        pause: vi.fn(),
        resume: vi.fn(),
        complete: vi.fn(),
        abandon: vi.fn(),
    },
}));

vi.mock('../../adaptive/api', () => ({
    adaptiveApi: {
        list: vi.fn().mockResolvedValue({ observations: [] }),
        checkIn: vi.fn(),
        burnout: vi.fn().mockResolvedValue({ signal: { level: 'none', score: 0, reason: 'on track' } }),
    },
}));

vi.mock('../../recharge/api', () => ({
    rechargeApi: {
        status: vi.fn().mockResolvedValue({
            recharge: null,
            cue_available: false,
            completed_focus_today: 0,
            due_recharges: 0,
            completed_recharges_today: 0,
            recharge_minutes_today: 0,
            productive_minutes_today: 0,
            work_ratio: 0,
            recharge_ratio: 0,
        }),
        start: vi.fn(),
        pause: vi.fn(),
        resume: vi.fn(),
        complete: vi.fn(),
        abandon: vi.fn(),
    },
}));

import TodayView from '../TodayView.vue';
import { todayApi } from '../api';
import { useShellStore } from '../../shell/store';
import { useToastStore } from '../../components/toast';
import ExecutionTimer from '../../execution/ExecutionTimer.vue';
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

    it('shows the NOW card with task, lock, and timeline events', async () => {        vi.mocked(todayApi.today).mockResolvedValue(response);
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

    it('completes with a full cascade: toast, progress reload, next-task emphasis (TASK-P17-011)', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-19T09:30:00'));
        const cascadeResponse: TodayResponse = JSON.parse(JSON.stringify(response));
        cascadeResponse.events.push({
            assignment: { ...cascadeResponse.events[0].assignment, id: 2, start_at: '2026-08-19T11:00:00', end_at: '2026-08-19T12:00:00', locked: false },
            locked: false,
            conflict: false,
            task: { ...cascadeResponse.events[0].task!, id: 11, title: 'Review notes', version: 1 },
            program: null,
            goal: null,
            milestone: null,
        });
        vi.mocked(todayApi.today).mockResolvedValue(cascadeResponse);
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        const nextCard = () => wrapper.find('[data-testid="next-card"]');
        expect(nextCard().exists()).toBe(true);
        expect(nextCard().classes()).not.toContain('ring-2');

        wrapper.findComponent(ExecutionTimer).vm.$emit('completed');
        await flushPromises();

        // 1) activity toast answers "did my action work?"
        const toast = useToastStore();
        expect(toast.items.some((t) => t.message.includes('Task completed'))).toBe(true);
        // today reloads → progress advance
        expect(todayApi.today).toHaveBeenCalledTimes(2);
        // 2) the upcoming task is spotlighted
        expect(nextCard().classes()).toContain('ring-2');

        // emphasis fades
        await vi.advanceTimersByTimeAsync(2000);
        expect(nextCard().classes()).not.toContain('ring-2');
        vi.useRealTimers();
    });

    it('keeps the control-center hierarchy NOW → NEXT → Timeline → context (TASK-P17-014)', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-19T09:30:00'));
        const resp: TodayResponse = JSON.parse(JSON.stringify(response));
        resp.events.push({
            assignment: { ...resp.events[0].assignment, id: 2, start_at: '2026-08-19T11:00:00', end_at: '2026-08-19T12:00:00', locked: false },
            locked: false,
            conflict: false,
            task: { ...resp.events[0].task!, id: 11, title: 'Review notes', version: 1 },
            program: null,
            goal: null,
            milestone: null,
        });
        vi.mocked(todayApi.today).mockResolvedValue(resp);
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        const html = wrapper.html();
        const orderOf = (testid: string) => html.indexOf(`data-testid="${testid}"`);
        const now = orderOf('now-card');
        const next = orderOf('next-card');
        const timeline = orderOf('today-timeline');
        expect(now).toBeGreaterThanOrEqual(0);
        expect(now).toBeLessThan(next);
        expect(next).toBeLessThan(timeline);
        // Supporting context comes strictly after the timeline.
        expect(timeline).toBeLessThan(orderOf('today-progress'));
        expect(timeline).toBeLessThan(orderOf('adaptive-context'));
        expect(timeline).toBeLessThan(orderOf('quick-capture'));
        vi.useRealTimers();
    });

    it('surfaces day progress as supporting context (TASK-P17-014)', async () => {
        const resp: TodayResponse = JSON.parse(JSON.stringify(response));
        resp.events.push({
            assignment: { ...resp.events[0].assignment, id: 2, start_at: '2026-08-19T11:00:00', end_at: '2026-08-19T12:00:00', locked: false },
            locked: false,
            conflict: false,
            task: { ...resp.events[0].task!, id: 11, title: 'Review notes', status: 'completed', version: 1 },
            program: null,
            goal: null,
            milestone: null,
        });
        vi.mocked(todayApi.today).mockResolvedValue(resp);
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        const strip = wrapper.find('[data-testid="today-progress"]');
        expect(strip.exists()).toBe(true);
        expect(wrapper.find('[data-testid="today-progress-count"]').text()).toContain('1/2');
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

    it('runs Mini Pause from the NOW card and reloads the day', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-19T09:30:00'));
        vi.mocked(todayApi.today).mockResolvedValue(response);
        vi.mocked(todayApi.miniPause).mockResolvedValue({
            version: 6,
            applied: true,
            moves: [
                {
                    task_id: '10',
                    title: 'Write report',
                    from: { start: '2026-08-19T09:00:00', end: '2026-08-19T10:00:00' },
                    to: { start: '2026-08-20T09:00:00', end: '2026-08-20T10:00:00' },
                },
            ],
            conflict_task_ids: [],
            explanation: 'Mini Pause: moved 1 task(s) to 2026-08-20: "Write report".',
        });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="now-card"]').exists()).toBe(true);

        await wrapper.find('[data-testid="mini-pause-button"]').trigger('click');
        await flushPromises();

        expect(todayApi.miniPause).toHaveBeenCalledWith({ date: '2026-08-19' });
        expect(todayApi.today).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[data-testid="mini-pause-message"]').text()).toContain('2026-08-20');
        vi.useRealTimers();
    });

    it('opens the Emergency Pause dialog, confirms, and shows the result', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-19T09:30:00'));
        vi.mocked(todayApi.today).mockResolvedValue(response);
        vi.mocked(todayApi.weekRange).mockResolvedValue({
            from: '2026-08-17',
            to: '2026-08-23',
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
        });
        vi.mocked(todayApi.emergencyPause).mockResolvedValue({
            version: 6,
            applied: true,
            week_start: '2026-08-17',
            week_end: '2026-08-23',
            keep_task_ids: ['10'],
            moves: [
                {
                    task_id: '10',
                    title: 'Write report',
                    from: { start: '2026-08-19T09:00:00', end: '2026-08-19T10:00:00' },
                    to: { start: '2026-08-26T09:00:00', end: '2026-08-26T10:00:00' },
                },
            ],
            conflict_task_ids: [],
            explanation: 'Emergency Pause: 2026-08-17 to 2026-08-23 marked as an exceptional recovery week; moved 1 task(s).',
        });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="emergency-pause-button"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="emergency-pause-dialog"]').exists()).toBe(true);
        expect(todayApi.weekRange).toHaveBeenCalledWith('2026-08-17', '2026-08-23');
        expect(wrapper.find('[data-testid="ep-confirm"]').exists()).toBe(true);

        await wrapper.find('[data-testid="ep-confirm"]').trigger('click');
        await flushPromises();

        expect(todayApi.emergencyPause).toHaveBeenCalledWith({
            date: '2026-08-19',
            keep_task_ids: [10],
        });
        expect(todayApi.today).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[data-testid="emergency-pause-message"]').text()).toContain('Emergency Pause');
        vi.useRealTimers();
    });

    it('shows a recovery banner when the week is tagged exceptional', async () => {
        const exceptional: TodayResponse = {
            ...response,
            pause: {
                type: 'emergency',
                week_start: '2026-08-17',
                week_end: '2026-08-23',
                keep_task_ids: [],
                moved_task_ids: ['10'],
                conflict_task_ids: [],
                schedule_version: 6,
            },
        };
        vi.mocked(todayApi.today).mockResolvedValue(exceptional);
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="recovery-banner"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="recovery-banner"]').text()).toContain('exceptional');
    });

    it('opens the Break Mode dialog, confirms, and shows the result', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-19T09:30:00'));
        vi.mocked(todayApi.today).mockResolvedValue(response);
        vi.mocked(todayApi.startBreak).mockResolvedValue({
            break_period_id: 7,
            start_date: '2026-08-17',
            end_date: '2026-08-21',
            explanation: 'Break Mode confirmed for 2026-08-17 to 2026-08-21 (5 days). Notifications are suppressed and the covered weeks are excluded from capacity estimates.',
        });

        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="break-mode-button"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="break-mode-dialog"]').exists()).toBe(true);

        await wrapper.find('[data-testid="bk-confirm"]').trigger('click');
        await flushPromises();

        expect(todayApi.startBreak).toHaveBeenCalledWith({
            start_date: '2026-08-19',
            end_date: '2026-08-19',
        });
        expect(todayApi.today).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[data-testid="break-message"]').text()).toContain('Break Mode confirmed');
        vi.useRealTimers();
    });

    it('shows a break banner when an active break covers the week', async () => {
        const breaking: TodayResponse = {
            ...response,
            break: {
                id: 7,
                user_id: 1,
                start_date: '2026-08-17',
                end_date: '2026-08-21',
                status: 'active',
                duration_days: 5,
            },
        };
        vi.mocked(todayApi.today).mockResolvedValue(breaking);
        vi.mocked(todayApi.endBreak).mockResolvedValue({
            applied: true,
            break_period_id: 7,
            start_date: '2026-08-17',
            end_date: '2026-08-21',
            duration_days: 5,
            explanation: 'Break Mode ended. The break covered 2026-08-17 to 2026-08-21 (5 days); the covered weeks are no longer tagged exceptional.',
        });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="break-banner"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="break-banner"]').text()).toContain('on break');

        await wrapper.find('[data-testid="end-break-button"]').trigger('click');
        await flushPromises();

        expect(todayApi.endBreak).toHaveBeenCalledTimes(1);
        expect(todayApi.today).toHaveBeenCalledTimes(2);
    });

    it('opens the Boost dialog, saves a target, and shows the result', async () => {
        const breaking: TodayResponse = {
            ...response,
            break: {
                id: 7,
                user_id: 1,
                start_date: '2026-08-17',
                end_date: '2026-08-21',
                status: 'active',
                duration_days: 5,
            },
        };
        vi.mocked(todayApi.today).mockResolvedValue(breaking);
        vi.mocked(todayApi.getBoostSetup).mockResolvedValue({
            eligible: true,
            active_target: null,
            recommendation: {
                eligible: true,
                recommendation: 'BOOST_AVAILABLE',
                reason: 'Average realization exceeds 90% with no burnout signal; Boost available.',
                average_realization: 0.95,
                recommended_target_percent: 70,
            },
            safety_cap_percent: 70,
            break_period_id: 7,
            break_start_date: '2026-08-17',
            break_end_date: '2026-08-21',
        });
        vi.mocked(todayApi.setBoostTarget).mockResolvedValue({
            target: {
                id: 11,
                user_id: 1,
                break_period_id: 7,
                start_date: '2026-08-17',
                end_date: '2026-08-21',
                target_percent: 70,
                status: 'active',
            },
            capped: false,
            warning: null,
            explanation: 'Boost target set to 70% of capacity for 2026-08-17 to 2026-08-21.',
        });

        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="boost-mode-button"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="boost-dialog"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="boost-recommended"]').text()).toBe('70%');

        await wrapper.find('[data-testid="boost-save"]').trigger('click');
        await flushPromises();

        expect(todayApi.setBoostTarget).toHaveBeenCalledWith({
            target_percent: 70,
            break_period_id: 7,
            start_date: '2026-08-17',
            end_date: '2026-08-21',
        });
        expect(todayApi.today).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[data-testid="boost-message"]').text()).toContain('Boost target set');
    });

    it('ends the active boost target from the break banner', async () => {
        const breaking: TodayResponse = {
            ...response,
            break: {
                id: 7,
                user_id: 1,
                start_date: '2026-08-17',
                end_date: '2026-08-21',
                status: 'active',
                duration_days: 5,
            },
        };
        vi.mocked(todayApi.today).mockResolvedValue(breaking);
        vi.mocked(todayApi.endBoostTarget).mockResolvedValue({
            applied: true,
            target_id: 11,
            target_percent: 70,
            start_date: '2026-08-17',
            end_date: '2026-08-21',
            explanation: 'Boost target ended; scheduling returns to the baseline target.',
        });

        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(TodayView, {
            props: { date: '2026-08-19' },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="end-boost-button"]').trigger('click');
        await flushPromises();

        expect(todayApi.endBoostTarget).toHaveBeenCalledTimes(1);
        expect(todayApi.today).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[data-testid="boost-message"]').text()).toContain('Boost target ended');
    });
});
