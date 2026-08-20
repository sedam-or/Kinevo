import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        taskApi: {
            list: vi.fn(),
            create: vi.fn(),
            show: vi.fn(),
            update: vi.fn(),
            setStatus: vi.fn(),
            partialComplete: vi.fn(),
            subtasks: vi.fn(),
            addSubtask: vi.fn(),
            toggleSubtask: vi.fn(),
            promoteSubtask: vi.fn(),
        },
    };
});

vi.mock('../../attachments/api', () => ({
    attachmentApi: {
        rules: vi.fn(),
        list: vi.fn(),
        upload: vi.fn(),
        remove: vi.fn(),
        download: vi.fn(),
    },
}));

import TaskListView from '../TaskListView.vue';
import TaskDetailView from '../TaskDetailView.vue';
import { taskApi } from '../api';
import { attachmentApi } from '../../attachments/api';
import type { Task } from '../types';

const task: Task = {
    id: 1,
    user_id: 1,
    program_id: null,
    goal_id: null,
    milestone_id: null,
    title: 'Write report',
    description: null,
    status: 'backlog',
    priority_tier: 1,
    estimated_minutes: 60,
    due_at: null,
    progress: 0,
    version: 1,
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('TaskListView', () => {
    it('loads and lists tasks with valid transitions', async () => {
        vi.mocked(taskApi.list).mockResolvedValue({ tasks: [task] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TaskListView, { global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="task-item"]').exists()).toBe(true);
        // backlog allows scheduled/in_progress/completed/skipped
        expect(wrapper.find('[data-testid="task-to-in_progress"]').exists()).toBe(true);
    });

    it('emits select when a task is opened', async () => {
        vi.mocked(taskApi.list).mockResolvedValue({ tasks: [task] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TaskListView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="task-open"]').trigger('click');
        expect(wrapper.emitted('select')?.[0]).toEqual([1]);
    });
});

describe('TaskDetailView', () => {
    it('loads a task and renders subtasks and actions', async () => {
        vi.mocked(taskApi.show).mockResolvedValue({ task: { ...task, status: 'in_progress' } });
        vi.mocked(taskApi.subtasks).mockResolvedValue({ subtasks: [{ id: 5, user_id: 1, task_id: 1, title: 'Draft', notes: null, sequence: 0, completed: false, version: 1 }] });
        vi.mocked(attachmentApi.rules).mockResolvedValue({ max_per_task: 3, max_bytes: 5 * 1024 * 1024, allowed_extensions: ['jpg', 'jpeg', 'png', 'pdf'], allowed_mime: ['image/jpeg', 'image/png', 'application/pdf'] });
        vi.mocked(attachmentApi.list).mockResolvedValue({ attachments: [] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TaskDetailView, { props: { taskId: 1 }, global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="task-edit"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="subtask-item"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="task-partial-complete"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="attachments"]').exists()).toBe(true);
    });

    it('renders evidence attachments on a completed task', async () => {
        vi.mocked(taskApi.show).mockResolvedValue({ task: { ...task, status: 'completed' } });
        vi.mocked(taskApi.subtasks).mockResolvedValue({ subtasks: [] });
        vi.mocked(attachmentApi.rules).mockResolvedValue({ max_per_task: 3, max_bytes: 5 * 1024 * 1024, allowed_extensions: ['jpg', 'jpeg', 'png', 'pdf'], allowed_mime: ['image/jpeg', 'image/png', 'application/pdf'] });
        vi.mocked(attachmentApi.list).mockResolvedValue({
            attachments: [
                { id: 1, task_id: 1, filename: 'evidence.png', mime_type: 'image/png', size_bytes: 1024, sha256: 'a'.repeat(64), created_at: '2026-08-20T00:00:00+00:00' },
            ],
        });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TaskDetailView, { props: { taskId: 1 }, global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="attachment-item"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="attachment-file-input"]').exists()).toBe(true);

        await wrapper.find('[data-testid="attachment-delete"]').trigger('click');
        await flushPromises();
        expect(attachmentApi.remove).toHaveBeenCalledWith(1, 1);
    });

    it('emits back', async () => {
        vi.mocked(taskApi.show).mockResolvedValue({ task });
        vi.mocked(taskApi.subtasks).mockResolvedValue({ subtasks: [] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(TaskDetailView, { props: { taskId: 1 }, global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="task-detail-back"]').trigger('click');
        expect(wrapper.emitted('back')).toBeTruthy();
    });
});
