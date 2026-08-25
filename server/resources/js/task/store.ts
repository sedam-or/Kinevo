import { defineStore } from 'pinia';
import { useWorkspaceStore } from '../workspace/store';
import { ref } from 'vue';
import { taskApi } from './api';
import type { ApiError } from '../api/types';
import type { Subtask, Task, TaskStatusValue, UpdateTaskPayload } from './types';

export const useTaskStore = defineStore('task', () => {
    const tasks = ref<Task[]>([]);
    const current = ref<Task | null>(null);
    const subtasks = ref<Subtask[]>([]);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    async function loadList(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            // TASK-P19-013 — scope to the declared active workspace.
            const wid = useWorkspaceStore().activeWorkspaceId;
            const { tasks: list } = await taskApi.list(wid);
            tasks.value = list;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function load(taskId: number): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const { task } = await taskApi.show(taskId);
            current.value = task;
            const { subtasks: sub } = await taskApi.subtasks(taskId);
            subtasks.value = sub;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function create(payload: { title: string; description?: string | null; priority_tier?: number; estimated_minutes?: number | null; due_at?: string | null }): Promise<Task | null> {
        error.value = null;
        try {
            const { task } = await taskApi.create(payload);
            tasks.value = [task, ...tasks.value];
            return task;
        } catch (err) {
            error.value = err as ApiError;
            return null;
        }
    }

    async function setStatus(taskId: number, status: TaskStatusValue): Promise<void> {
        error.value = null;
        try {
            const { task } = await taskApi.setStatus(taskId, status);
            replaceInList(task);
            if (current.value?.id === taskId) {
                current.value = task;
            }
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function apiUpdate(taskId: number, payload: UpdateTaskPayload): Promise<void> {
        error.value = null;
        try {
            const { task } = await taskApi.update(taskId, payload);
            replaceInList(task);
            if (current.value?.id === taskId) {
                current.value = task;
            }
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function addSubtask(taskId: number, title: string): Promise<void> {
        error.value = null;
        try {
            const { subtask } = await taskApi.addSubtask(taskId, title);
            subtasks.value = [...subtasks.value, subtask];
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function toggleSubtask(taskId: number, subtaskId: number): Promise<void> {
        error.value = null;
        try {
            const { subtask, task } = await taskApi.toggleSubtask(taskId, subtaskId);
            subtasks.value = subtasks.value.map((s) => (s.id === subtask.id ? subtask : s));
            if (current.value?.id === taskId) {
                current.value = task;
            }
            replaceInList(task);
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function partialComplete(taskId: number): Promise<void> {
        error.value = null;
        try {
            const { task } = await taskApi.partialComplete(taskId);
            if (current.value?.id === taskId) {
                current.value = task;
            }
            replaceInList(task);
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function promoteSubtask(taskId: number, subtaskId: number): Promise<void> {
        error.value = null;
        try {
            const { source_task } = await taskApi.promoteSubtask(subtaskId);
            if (current.value?.id === taskId) {
                current.value = source_task;
            }
            replaceInList(source_task);
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    function replaceInList(task: Task): void {
        const index = tasks.value.findIndex((t) => t.id === task.id);
        if (index >= 0) {
            tasks.value[index] = task;
        }
    }

    function clearCurrent(): void {
        current.value = null;
        subtasks.value = [];
    }

    return {
        tasks,
        current,
        subtasks,
        loading,
        error,
        loadList,
        load,
        create,
        setStatus,
        apiUpdate,
        addSubtask,
        toggleSubtask,
        partialComplete,
        promoteSubtask,
        clearCurrent,
    };
});
