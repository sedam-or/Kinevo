import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api/client', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../../api/client')>();
    return {
        ...actual,
        apiClient: { request: vi.fn() },
    };
});

import QuickCapture from '../QuickCapture.vue';
import { useQuickCaptureStore } from '../store';
import { apiClient } from '../../api/client';

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

function defaultRequestMock(): void {
    vi.mocked(apiClient.request).mockImplementation((path: string) => {
        if (String(path).startsWith('/goals')) {
            return Promise.resolve({ goals: [] });
        }
        if (String(path).startsWith('/programs')) {
            return Promise.resolve({ programs: [] });
        }
        return Promise.resolve({});
    });
}

describe('QuickCapture', () => {
    it('renders the capture form with title/priority/size/duration fields', async () => {
        defaultRequestMock();
        const pinia = createPinia();
        setActivePinia(pinia);
        const qc = useQuickCaptureStore();
        qc.show();

        const wrapper = mount(QuickCapture, { global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="qc-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="qc-title"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="qc-priority"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="qc-size"]').exists()).toBe(true);
    });

    it('shows the three strategies when the slot is full', async () => {
        defaultRequestMock();
        const pinia = createPinia();
        setActivePinia(pinia);
        const qc = useQuickCaptureStore();
        qc.show();
        qc.lastResult = {
            task: { id: 1, title: 'Task', status: 'backlog' },
            placed: false,
            assignment: null,
            code: 'TASK_NO_CAPACITY',
            strategies: ['manual_swap', 'auto_swap', 'schedule_later'],
        };

        const wrapper = mount(QuickCapture, { global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="qc-no-capacity"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="qc-auto-swap"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="qc-manual-swap"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="qc-schedule-later"]').exists()).toBe(true);
    });

    it('shows placed confirmation when a slot was found', async () => {
        defaultRequestMock();
        const pinia = createPinia();
        setActivePinia(pinia);
        const qc = useQuickCaptureStore();
        qc.show();
        qc.lastResult = {
            task: { id: 1, title: 'Task', status: 'scheduled' },
            placed: true,
            assignment: { id: 1, task_id: 1, date: '2026-08-19', start_at: '2026-08-19T09:00:00', end_at: '2026-08-19T10:00:00' },
            code: 'PLACED',
            strategies: [],
        };

        const wrapper = mount(QuickCapture, { global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="qc-placed"]').exists()).toBe(true);
    });

    it('submits the capture form', async () => {
        vi.mocked(apiClient.request).mockImplementation((path: string) => {
            if (String(path).startsWith('/goals')) {
                return Promise.resolve({ goals: [] });
            }
            if (String(path).startsWith('/programs')) {
                return Promise.resolve({ programs: [] });
            }
            if (String(path) === '/quick-capture') {
                return Promise.resolve({
                    task: { id: 1, title: 'New task', status: 'scheduled' },
                    placed: true,
                    assignment: null,
                    code: 'PLACED',
                    strategies: [],
                });
            }
            return Promise.resolve({});
        });
        const pinia = createPinia();
        setActivePinia(pinia);
        const qc = useQuickCaptureStore();
        qc.show();

        const wrapper = mount(QuickCapture, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="qc-title"]').setValue('New task');
        await wrapper.find('[data-testid="qc-submit"]').trigger('submit');
        await flushPromises();

        expect(qc.lastResult?.code).toBe('PLACED');
    });
});
