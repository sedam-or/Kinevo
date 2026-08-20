import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        executionApi: {
            active: vi.fn(),
            start: vi.fn(),
            pause: vi.fn(),
            resume: vi.fn(),
            complete: vi.fn(),
            abandon: vi.fn(),
        },
    };
});

import ExecutionTimer from '../ExecutionTimer.vue';
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

describe('ExecutionTimer', () => {
    it('shows Start when there is no active session', async () => {
        vi.mocked(executionApi.active).mockResolvedValue({ execution: null });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ExecutionTimer, {
            props: { taskId: 10 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="execution-start"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="execution-pause"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="execution-complete"]').exists()).toBe(false);
    });

    it('shows running controls when a timer is active', async () => {
        vi.mocked(executionApi.active).mockResolvedValue({ execution: running });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ExecutionTimer, {
            props: { taskId: 10 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="execution-status"]').text()).toBe('Running');
        expect(wrapper.find('[data-testid="execution-pause"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="execution-complete"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="execution-abandon"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="execution-start"]').exists()).toBe(false);
    });

    it('pauses and then resumes', async () => {
        vi.mocked(executionApi.active).mockResolvedValue({ execution: running });
        vi.mocked(executionApi.pause).mockResolvedValue({
            execution: { ...running, status: 'paused', last_resumed_at: null, accumulated_seconds: 60 },
        });
        vi.mocked(executionApi.resume).mockResolvedValue({ execution: running });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ExecutionTimer, {
            props: { taskId: 10 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="execution-pause"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="execution-status"]').text()).toBe('Paused');
        expect(wrapper.find('[data-testid="execution-resume"]').exists()).toBe(true);

        await wrapper.find('[data-testid="execution-resume"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="execution-status"]').text()).toBe('Running');
    });

    it('emits completed when the timer completes', async () => {
        vi.mocked(executionApi.active).mockResolvedValue({ execution: running });
        vi.mocked(executionApi.complete).mockResolvedValue({
            execution: { ...running, status: 'completed', accumulated_seconds: 2700, ended_at: '2026-08-19T09:45:00' },
            focus_session: { id: 1, user_id: 1, task_id: 10, started_at: '2026-08-19T09:00:00', ended_at: '2026-08-19T09:45:00', duration_minutes: 45 },
            task: { id: 10, status: 'completed', progress: 100, version: 2, title: 'Write report' },
            continuation: null,
        });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ExecutionTimer, {
            props: { taskId: 10 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="execution-complete"]').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('completed')).toBeTruthy();
    });

    it('shows an error when an action fails', async () => {
        vi.mocked(executionApi.active).mockResolvedValue({ execution: null });
        vi.mocked(executionApi.start).mockRejectedValue({ code: 'VALIDATION', status: 422, message: 'An execution timer is already running.', retryable: false });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ExecutionTimer, {
            props: { taskId: 10 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="execution-start"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="execution-error"]').text()).toContain('already running');
    });
});