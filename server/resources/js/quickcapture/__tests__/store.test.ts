import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../../api/client', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../../api/client')>();
    return {
        ...actual,
        apiClient: {
            request: vi.fn(),
        },
    };
});

import { useQuickCaptureStore } from '../store';
import { apiClient } from '../../api/client';

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('quick capture store', () => {
    it('opens and closes', () => {
        const store = useQuickCaptureStore();
        store.show();
        expect(store.open).toBe(true);
        store.hide();
        expect(store.open).toBe(false);
    });

    it('loads goal/program context for the dropdowns', async () => {
        vi.mocked(apiClient.request).mockResolvedValueOnce({ goals: [{ id: 1, title: 'G' }] });
        vi.mocked(apiClient.request).mockResolvedValueOnce({ programs: [{ id: 2, name: 'P' }] });
        const store = useQuickCaptureStore();
        await store.loadContext();
        expect(store.goals).toHaveLength(1);
        expect(store.programs).toHaveLength(1);
    });

    it('submits a quick capture and stores PLACED result', async () => {
        vi.mocked(apiClient.request).mockResolvedValue({
            task: { id: 1, title: 'Task', status: 'scheduled' },
            placed: true,
            assignment: { id: 1, task_id: 1, date: '2026-08-19', start_at: '2026-08-19T09:00:00', end_at: '2026-08-19T10:00:00' },
            code: 'PLACED',
            strategies: [],
        });
        const store = useQuickCaptureStore();
        const result = await store.submit({ title: 'Task' });
        expect(result?.code).toBe('PLACED');
        expect(store.lastResult?.placed).toBe(true);
    });

    it('stores TASK_NO_CAPACITY with the three strategies', async () => {
        vi.mocked(apiClient.request).mockResolvedValue({
            task: { id: 1, title: 'Task', status: 'backlog' },
            placed: false,
            assignment: null,
            code: 'TASK_NO_CAPACITY',
            strategies: ['manual_swap', 'auto_swap', 'schedule_later'],
        });
        const store = useQuickCaptureStore();
        const result = await store.submit({ title: 'Task' });
        expect(result?.code).toBe('TASK_NO_CAPACITY');
        expect(result?.strategies).toEqual(['manual_swap', 'auto_swap', 'schedule_later']);
    });

    it('runs an auto swap and records the explanation', async () => {
        vi.mocked(apiClient.request).mockResolvedValue({
            task: { id: 1, title: 'Task', status: 'scheduled' },
            applied: true,
            assignment: { id: 1, task_id: 1, date: '2026-08-19', start_at: '2026-08-19T09:00:00', end_at: '2026-08-19T10:00:00' },
            swapped_task: null,
            explanation: 'Moved task.',
        });
        const store = useQuickCaptureStore();
        const result = await store.autoSwap(1, '2026-08-19', 45);
        expect(result?.applied).toBe(true);
        expect(store.autoSwapResult?.explanation).toBe('Moved task.');
    });
});
