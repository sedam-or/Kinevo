import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        knowledgeLinkApi: {
            linksForNote: vi.fn(),
            createForNote: vi.fn(),
            removeFromNote: vi.fn(),
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

import LinkManager from '../LinkManager.vue';
import { knowledgeLinkApi } from '../api';
import { useShellStore } from '../../shell/store';

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('LinkManager', () => {
    it('loads links and context on mount', async () => {
        vi.mocked(knowledgeLinkApi.linksForNote).mockResolvedValue({ links: [] });
        vi.mocked(knowledgeLinkApi.goals).mockResolvedValue({ goals: [{ id: 1, title: 'Learn Laravel' }] });
        vi.mocked(knowledgeLinkApi.programs).mockResolvedValue({ programs: [{ id: 2, name: 'Writing' }] });
        vi.mocked(knowledgeLinkApi.tasks).mockResolvedValue({ tasks: [{ id: 3, title: 'Task A' }] });
        vi.mocked(knowledgeLinkApi.canvases).mockResolvedValue({ canvases: [{ id: 4, title: 'Map' }] });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(LinkManager, {
            props: { noteId: 1 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(knowledgeLinkApi.linksForNote).toHaveBeenCalledWith(1);
        expect(wrapper.find('[data-testid="no-links"]').exists()).toBe(true);
    });

    it('renders existing links with label and remove button', async () => {
        vi.mocked(knowledgeLinkApi.linksForNote).mockResolvedValue({
            links: [{ id: 1, user_id: 1, source_type: 'note', source_id: 1, target_type: 'goal', target_id: 9, link_type: 'supports' }],
        });
        vi.mocked(knowledgeLinkApi.goals).mockResolvedValue({ goals: [{ id: 9, title: 'Learn Laravel' }] });
        vi.mocked(knowledgeLinkApi.programs).mockResolvedValue({ programs: [] });
        vi.mocked(knowledgeLinkApi.tasks).mockResolvedValue({ tasks: [] });
        vi.mocked(knowledgeLinkApi.canvases).mockResolvedValue({ canvases: [] });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(LinkManager, {
            props: { noteId: 1 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        const item = wrapper.find('[data-testid="knowledge-link-item"]');
        expect(item.exists()).toBe(true);
        expect(item.text()).toContain('Learn Laravel');
        expect(wrapper.find('[data-testid="link-remove-1"]').exists()).toBe(true);
    });

    it('opens a linked entity on its own surface (TASK-P17-002)', async () => {
        vi.mocked(knowledgeLinkApi.linksForNote).mockResolvedValue({
            links: [
                { id: 1, user_id: 1, source_type: 'note', source_id: 1, target_type: 'goal', target_id: 9, link_type: 'supports' },
                { id: 2, user_id: 1, source_type: 'note', source_id: 1, target_type: 'task', target_id: 3, link_type: 'references' },
            ],
        });
        vi.mocked(knowledgeLinkApi.goals).mockResolvedValue({ goals: [{ id: 9, title: 'Learn Laravel' }] });
        vi.mocked(knowledgeLinkApi.programs).mockResolvedValue({ programs: [] });
        vi.mocked(knowledgeLinkApi.tasks).mockResolvedValue({ tasks: [{ id: 3, title: 'Task A' }] });
        vi.mocked(knowledgeLinkApi.canvases).mockResolvedValue({ canvases: [] });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(LinkManager, {
            props: { noteId: 1 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        const shell = useShellStore();
        await wrapper.find('[data-testid="link-open-goal-9"]').trigger('click');
        expect(shell.activeView).toBe('goals');
        expect(shell.viewFocus['goals']).toBe(9);

        await wrapper.find('[data-testid="link-open-task-3"]').trigger('click');
        expect(shell.activeView).toBe('tasks');
        expect(shell.viewFocus['tasks']).toBe(3);
    });

    it('creates a goal link via the form', async () => {
        vi.mocked(knowledgeLinkApi.linksForNote).mockResolvedValue({ links: [] });
        vi.mocked(knowledgeLinkApi.goals).mockResolvedValue({ goals: [{ id: 9, title: 'Learn Laravel' }] });
        vi.mocked(knowledgeLinkApi.programs).mockResolvedValue({ programs: [] });
        vi.mocked(knowledgeLinkApi.tasks).mockResolvedValue({ tasks: [] });
        vi.mocked(knowledgeLinkApi.canvases).mockResolvedValue({ canvases: [] });
        vi.mocked(knowledgeLinkApi.createForNote).mockResolvedValue({
            link: { id: 5, user_id: 1, source_type: 'note', source_id: 1, target_type: 'goal', target_id: 9, link_type: 'supports' },
        });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(LinkManager, {
            props: { noteId: 1 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="link-target-type"]').setValue('goal');
        await flushPromises();
        await wrapper.find('[data-testid="link-target-id"]').setValue('9');
        await wrapper.find('[data-testid="link-type"]').setValue('supports');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(knowledgeLinkApi.createForNote).toHaveBeenCalledWith(1, {
            target_type: 'goal',
            target_id: 9,
            link_type: 'supports',
        });
        expect(wrapper.find('[data-testid="knowledge-link-item"]').exists()).toBe(true);
    });

    it('shows a conflict message on a duplicate link', async () => {
        vi.mocked(knowledgeLinkApi.linksForNote).mockResolvedValue({ links: [] });
        vi.mocked(knowledgeLinkApi.goals).mockResolvedValue({ goals: [{ id: 9, title: 'Learn Laravel' }] });
        vi.mocked(knowledgeLinkApi.programs).mockResolvedValue({ programs: [] });
        vi.mocked(knowledgeLinkApi.tasks).mockResolvedValue({ tasks: [] });
        vi.mocked(knowledgeLinkApi.canvases).mockResolvedValue({ canvases: [] });
        vi.mocked(knowledgeLinkApi.createForNote).mockRejectedValue({
            code: 'CONFLICT',
            status: 409,
            message: 'duplicate',
            retryable: false,
        });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(LinkManager, {
            props: { noteId: 1 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="link-target-type"]').setValue('goal');
        await flushPromises();
        await wrapper.find('[data-testid="link-target-id"]').setValue('9');
        await wrapper.find('[data-testid="link-type"]').setValue('supports');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[data-testid="link-form-error"]').text()).toContain('already exists');
    });

    it('removes a link', async () => {
        vi.mocked(knowledgeLinkApi.linksForNote).mockResolvedValue({
            links: [{ id: 1, user_id: 1, source_type: 'note', source_id: 1, target_type: 'goal', target_id: 9, link_type: 'supports' }],
        });
        vi.mocked(knowledgeLinkApi.goals).mockResolvedValue({ goals: [{ id: 9, title: 'Learn Laravel' }] });
        vi.mocked(knowledgeLinkApi.programs).mockResolvedValue({ programs: [] });
        vi.mocked(knowledgeLinkApi.tasks).mockResolvedValue({ tasks: [] });
        vi.mocked(knowledgeLinkApi.canvases).mockResolvedValue({ canvases: [] });
        vi.mocked(knowledgeLinkApi.removeFromNote).mockResolvedValue(undefined);

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(LinkManager, {
            props: { noteId: 1 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="link-remove-1"]').trigger('click');
        await flushPromises();

        expect(knowledgeLinkApi.removeFromNote).toHaveBeenCalledWith(1, 1);
        expect(wrapper.find('[data-testid="no-links"]').exists()).toBe(true);
    });

    it('loads milestones when a goal is chosen for a milestone link', async () => {
        vi.mocked(knowledgeLinkApi.linksForNote).mockResolvedValue({ links: [] });
        vi.mocked(knowledgeLinkApi.goals).mockResolvedValue({ goals: [{ id: 1, title: 'G' }] });
        vi.mocked(knowledgeLinkApi.programs).mockResolvedValue({ programs: [] });
        vi.mocked(knowledgeLinkApi.tasks).mockResolvedValue({ tasks: [] });
        vi.mocked(knowledgeLinkApi.canvases).mockResolvedValue({ canvases: [] });
        vi.mocked(knowledgeLinkApi.milestones).mockResolvedValue({
            milestones: [{ id: 7, goal_id: 1, title: 'M1' }],
        });

        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(LinkManager, {
            props: { noteId: 1 },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        await wrapper.find('[data-testid="link-target-type"]').setValue('milestone');
        await flushPromises();
        await wrapper.find('[data-testid="link-goal"]').setValue('1');
        await flushPromises();

        expect(knowledgeLinkApi.milestones).toHaveBeenCalledWith(1);
        const options = wrapper.findAll('[data-testid="link-target-id"] option');
        expect(options.some((o) => o.text() === 'M1')).toBe(true);
    });
});
