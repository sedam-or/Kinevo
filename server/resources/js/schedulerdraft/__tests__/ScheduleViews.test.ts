import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        scheduleDraftApi: {
            generate: vi.fn(),
            applyDraft: vi.fn(),
            propose: vi.fn(),
            applyProposal: vi.fn(),
        },
    };
});

import ScheduleDraftView from '../ScheduleDraftView.vue';
import RescheduleView from '../RescheduleView.vue';
import { useScheduleDraftStore } from '../store';
import { scheduleDraftApi } from '../api';

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('ScheduleDraftView', () => {
    it('generates a draft and shows accepted and rejected tasks', async () => {
        vi.mocked(scheduleDraftApi.generate).mockResolvedValue({
            draft: {
                assignments: [{ task_id: '1', title: 'Write', start: '2026-08-17T09:00:00', end: '2026-08-17T10:00:00' }],
                unassigned: [{ task_id: '2', title: 'Call', reason: 'NO_AVAILABLE_SLOT' }],
            },
            base_version: 5,
        });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ScheduleDraftView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="draft-generate"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="draft-accepted-item"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="draft-rejected-item"]').exists()).toBe(true);
    });

    it('applies a draft', async () => {
        vi.mocked(scheduleDraftApi.generate).mockResolvedValue({
            draft: {
                assignments: [{ task_id: '1', title: 'Write', start: '2026-08-17T09:00:00', end: '2026-08-17T10:00:00' }],
                unassigned: [],
            },
            base_version: 5,
        });
        vi.mocked(scheduleDraftApi.applyDraft).mockResolvedValue({ version: 6, applied: true, assignments: [] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(ScheduleDraftView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="draft-generate"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="draft-apply"]').trigger('click');
        await flushPromises();

        const sd = useScheduleDraftStore();
        expect(sd.draftApplyResult?.applied).toBe(true);
    });
});

describe('RescheduleView', () => {
    it('proposes a reschedule and shows BEFORE/AFTER/REASON', async () => {
        vi.mocked(scheduleDraftApi.propose).mockResolvedValue({
            proposal: {
                base_version: 5,
                new_version: 6,
                moves: [
                    {
                        task_id: '1',
                        title: 'Write',
                        from: { start: '2026-08-17T09:00:00', end: '2026-08-17T10:00:00' },
                        to: { start: '2026-08-18T09:00:00', end: '2026-08-18T10:00:00' },
                    },
                ],
                conflict_task_ids: [],
            },
            has_changes: true,
        });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(RescheduleView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="reschedule-propose"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="reschedule-move"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="move-before"]').text()).toContain('Aug 17');
        expect(wrapper.find('[data-testid="move-after"]').text()).toContain('Aug 18');
        expect(wrapper.find('[data-testid="move-reason"]').text()).toContain('REASON');
    });

    it('shows conflicts when tasks could not be placed', async () => {
        vi.mocked(scheduleDraftApi.propose).mockResolvedValue({
            proposal: {
                base_version: 5,
                new_version: 6,
                moves: [],
                conflict_task_ids: ['9'],
            },
            has_changes: false,
        });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(RescheduleView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="reschedule-propose"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="reschedule-conflicts"]').exists()).toBe(true);
    });
});
