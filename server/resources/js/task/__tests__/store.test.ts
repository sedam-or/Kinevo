import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

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

import { useTaskStore } from '../store';
import { taskApi } from '../api';
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

describe('task store', () => {
    it('loads the task list', async () => {
        vi.mocked(taskApi.list).mockResolvedValue({ tasks: [task] });
        const store = useTaskStore();
        await store.loadList();
        expect(store.tasks).toHaveLength(1);
        expect(store.tasks[0].title).toBe('Write report');
    });

    it('loads a task with its subtasks', async () => {
        vi.mocked(taskApi.show).mockResolvedValue({ task });
        vi.mocked(taskApi.subtasks).mockResolvedValue({ subtasks: [{ id: 5, user_id: 1, task_id: 1, title: 'Draft', notes: null, sequence: 0, completed: false, version: 1 }] });
        const store = useTaskStore();
        await store.load(1);
        expect(store.current?.id).toBe(1);
        expect(store.subtasks).toHaveLength(1);
    });

    it('creates a task and prepends it', async () => {
        vi.mocked(taskApi.create).mockResolvedValue({ task });
        const store = useTaskStore();
        const created = await store.create({ title: 'Write report' });
        expect(created?.id).toBe(1);
        expect(store.tasks).toHaveLength(1);
    });

    it('applies a status transition via the backend', async () => {
        vi.mocked(taskApi.setStatus).mockResolvedValue({ task: { ...task, status: 'in_progress' } });
        const store = useTaskStore();
        store.tasks = [task];
        await store.setStatus(1, 'in_progress');
        expect(store.tasks[0].status).toBe('in_progress');
    });

    it('adds a subtask', async () => {
        vi.mocked(taskApi.addSubtask).mockResolvedValue({ subtask: { id: 2, user_id: 1, task_id: 1, title: 'New', notes: null, sequence: 0, completed: false, version: 1 } });
        const store = useTaskStore();
        await store.addSubtask(1, 'New');
        expect(store.subtasks).toHaveLength(1);
    });

    it('toggles a subtask and updates task progress', async () => {
        vi.mocked(taskApi.toggleSubtask).mockResolvedValue({
            subtask: { id: 2, user_id: 1, task_id: 1, title: 'New', notes: null, sequence: 0, completed: true, version: 1 },
            task: { ...task, progress: 100 },
        });
        const store = useTaskStore();
        store.subtasks = [{ id: 2, user_id: 1, task_id: 1, title: 'New', notes: null, sequence: 0, completed: false, version: 1 }];
        store.current = task;
        await store.toggleSubtask(1, 2);
        expect(store.subtasks[0].completed).toBe(true);
        expect(store.current?.progress).toBe(100);
    });
});
