import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../knowledge/api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../../knowledge/api')>();
    return {
        ...actual,
        knowledgeLinkApi: {
            linksForNote: vi.fn(),
            createForNote: vi.fn(),
            removeFromNote: vi.fn(),
            linksForCanvas: vi.fn(),
            createForCanvas: vi.fn(),
            removeFromCanvas: vi.fn(),
            reverseLinks: vi.fn(),
            goals: vi.fn(),
            programs: vi.fn(),
            tasks: vi.fn(),
            milestones: vi.fn(),
            canvases: vi.fn(),
            notes: vi.fn(),
        },
    };
});

import CanvasContextPanel from '../CanvasContextPanel.vue';
import { knowledgeLinkApi } from '../../knowledge/api';

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

function mockContext(): void {
    vi.mocked(knowledgeLinkApi.goals).mockResolvedValue({ goals: [{ id: 1, title: 'Learn Laravel' }] });
    vi.mocked(knowledgeLinkApi.programs).mockResolvedValue({ programs: [{ id: 2, name: 'Writing' }] });
    vi.mocked(knowledgeLinkApi.tasks).mockResolvedValue({ tasks: [{ id: 3, title: 'Task A' }] });
    vi.mocked(knowledgeLinkApi.canvases).mockResolvedValue({ canvases: [{ id: 4, title: 'Map' }] });
    vi.mocked(knowledgeLinkApi.notes).mockResolvedValue({ notes: [{ id: 5, title: 'Research' }] });
}

describe('CanvasContextPanel', () => {
    it('loads canvas links and context on mount', async () => {
        vi.mocked(knowledgeLinkApi.linksForCanvas).mockResolvedValue({ links: [] });
        mockContext();

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(CanvasContextPanel, {
            props: { canvasId: 3 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(knowledgeLinkApi.linksForCanvas).toHaveBeenCalledWith(3);
        expect(wrapper.find('[data-testid="canvas-context-empty"]').exists()).toBe(true);
    });

    it('renders existing canvas links with label and remove button', async () => {
        vi.mocked(knowledgeLinkApi.linksForCanvas).mockResolvedValue({
            links: [{ id: 1, user_id: 1, source_type: 'canvas', source_id: 3, target_type: 'goal', target_id: 1, link_type: 'supports' }],
        });
        mockContext();

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(CanvasContextPanel, {
            props: { canvasId: 3 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="canvas-context-item"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="canvas-context-item"]').text()).toContain('Goal:');
        expect(wrapper.find('[data-testid="canvas-context-item"]').text()).toContain('Learn Laravel');
        expect(wrapper.find('[data-testid="canvas-context-remove-1"]').exists()).toBe(true);
    });

    it('creates a canvas link to a note target', async () => {
        vi.mocked(knowledgeLinkApi.linksForCanvas).mockResolvedValue({ links: [] });
        mockContext();
        vi.mocked(knowledgeLinkApi.createForCanvas).mockResolvedValue({
            link: { id: 9, user_id: 1, source_type: 'canvas', source_id: 3, target_type: 'note', target_id: 5, link_type: 'related_to' },
        });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(CanvasContextPanel, {
            props: { canvasId: 3 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="canvas-context-target-type"]').setValue('note');
        await flushPromises();
        await wrapper.find('[data-testid="canvas-context-target-id"]').setValue('5');
        await wrapper.find('[data-testid="canvas-context-link-type"]').setValue('related_to');
        await wrapper.find('[data-testid="canvas-context-submit"]').trigger('submit');
        await flushPromises();

        expect(knowledgeLinkApi.createForCanvas).toHaveBeenCalledWith(3, {
            target_type: 'note',
            target_id: 5,
            link_type: 'related_to',
        });
        expect(wrapper.find('[data-testid="canvas-context-item"]').exists()).toBe(true);
    });

    it('surfaces a duplicate link error (409)', async () => {
        vi.mocked(knowledgeLinkApi.linksForCanvas).mockResolvedValue({ links: [] });
        mockContext();
        vi.mocked(knowledgeLinkApi.createForCanvas).mockRejectedValue({
            code: 'CONFLICT',
            status: 409,
            message: 'duplicate',
            retryable: false,
        });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(CanvasContextPanel, {
            props: { canvasId: 3 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="canvas-context-target-type"]').setValue('goal');
        await flushPromises();
        await wrapper.find('[data-testid="canvas-context-target-id"]').setValue('1');
        await wrapper.find('[data-testid="canvas-context-link-type"]').setValue('supports');
        await wrapper.find('[data-testid="canvas-context-submit"]').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[data-testid="canvas-context-form-error"]').text()).toContain('already exists');
    });

    it('removes a canvas link', async () => {
        vi.mocked(knowledgeLinkApi.linksForCanvas).mockResolvedValue({
            links: [{ id: 1, user_id: 1, source_type: 'canvas', source_id: 3, target_type: 'goal', target_id: 1, link_type: 'supports' }],
        });
        mockContext();
        vi.mocked(knowledgeLinkApi.removeFromCanvas).mockResolvedValue(undefined);

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(CanvasContextPanel, {
            props: { canvasId: 3 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="canvas-context-remove-1"]').trigger('click');
        await flushPromises();

        expect(knowledgeLinkApi.removeFromCanvas).toHaveBeenCalledWith(3, 1);
        expect(wrapper.find('[data-testid="canvas-context-empty"]').exists()).toBe(true);
    });

    it('loads milestone targets dependent on a selected goal', async () => {
        vi.mocked(knowledgeLinkApi.linksForCanvas).mockResolvedValue({ links: [] });
        mockContext();
        vi.mocked(knowledgeLinkApi.milestones).mockResolvedValue({ milestones: [{ id: 6, goal_id: 1, title: 'Milestone X' }] });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(CanvasContextPanel, {
            props: { canvasId: 3 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="canvas-context-target-type"]').setValue('milestone');
        await flushPromises();
        await wrapper.find('[data-testid="canvas-context-goal"]').setValue('1');
        await flushPromises();

        expect(knowledgeLinkApi.milestones).toHaveBeenCalledWith(1);
        const target = wrapper.find('[data-testid="canvas-context-target-id"]');
        expect(target.text()).toContain('Milestone X');
    });
});