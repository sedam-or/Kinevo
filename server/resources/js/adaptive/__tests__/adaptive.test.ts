import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { adaptiveApi } from '../api';
import { useAdaptiveStore } from '../store';
import AdaptiveContextPanel from '../AdaptiveContextPanel.vue';

vi.mock('../api', () => ({
    adaptiveApi: {
        list: vi.fn(),
        checkIn: vi.fn(),
        burnout: vi.fn(),
    },
}));

const observation = {
    id: 1,
    user_id: 1,
    task_id: null,
    energy_level: 7,
    stress_level: null,
    task_difficulty: null,
    skill_familiarity: null,
    interruption_count: null,
    focus_duration_minutes: null,
    checked_at: '2026-08-21T10:00:00+00:00',
};

describe('useAdaptiveStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('loads observations from the API', async () => {
        vi.mocked(adaptiveApi.list).mockResolvedValue({ observations: [observation] });
        const store = useAdaptiveStore();
        await store.load();
        expect(store.observations).toHaveLength(1);
        expect(store.latest?.energy_level).toBe(7);
    });

    it('records a check-in and prepends it', async () => {
        vi.mocked(adaptiveApi.checkIn).mockResolvedValue({ observation });
        const store = useAdaptiveStore();
        const saved = await store.checkIn({ energy_level: 7 });
        expect(saved?.id).toBe(1);
        expect(store.observations[0]?.energy_level).toBe(7);
    });

    it('surfaces API errors instead of throwing', async () => {
        vi.mocked(adaptiveApi.checkIn).mockRejectedValue({ code: 'SERVER', status: 500, message: 'boom', retryable: true });
        const store = useAdaptiveStore();
        const saved = await store.checkIn({ energy_level: 3 });
        expect(saved).toBeNull();
        expect(store.error?.message).toBe('boom');
    });
});

describe('AdaptiveContextPanel', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        vi.mocked(adaptiveApi.list).mockResolvedValue({ observations: [] });
        vi.mocked(adaptiveApi.burnout).mockResolvedValue({
            signal: { level: 'none', score: 0, reason: 'on track' },
        });
    });

    it('renders the lightweight check-in surface', async () => {
        const wrapper = mount(AdaptiveContextPanel, { global: { plugins: [createPinia()] } });
        await flushPromises();
        expect(wrapper.find('[data-testid="adaptive-context"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="adaptive-energy-5"]').exists()).toBe(true);
    });

    it('submits the selected energy level', async () => {
        vi.mocked(adaptiveApi.checkIn).mockResolvedValue({ observation });
        const pinia = createPinia();
        setActivePinia(pinia);
        const wrapper = mount(AdaptiveContextPanel, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="adaptive-energy-7"]').trigger('click');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(adaptiveApi.checkIn).toHaveBeenCalledWith({ energy_level: 7, task_id: null });
    });
});