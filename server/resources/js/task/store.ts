import { defineStore } from 'pinia';
import { useWorkspaceStore } from '../workspace/store';
import { ref } from 'vue';
import { taskApi } from './api';
import { submitOfflineAware } from '../offline/reconcile-submit';
import { getOfflineQueue } from '../offline/queue-access';
import type { ApiError } from '../api/types';
import type { Subtask, SubtaskResponse, Task, TaskResponse, TaskStatusValue, UpdateTaskPayload } from './types';

export const useTaskStore = defineStore('task', () => {
    const tasks = ref<Task[]>([]);
    const current = ref<Task | null>(null);
    const subtasks = ref<Subtask[]>([]);
    /** ADR-015: active placement lock state for the loaded task (null = no placement). */
    const assignmentLocked = ref<boolean | null>(null);
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
            const { task, assignment_locked: assignmentLockedFromApi } = await taskApi.show(taskId);
            current.value = task;
            assignmentLocked.value = assignmentLockedFromApi ?? null;
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
        const queue = getOfflineQueue();
        try {
            if (queue !== null) {
                const result = await submitOfflineAware<TaskResponse>(
                    queue,
                    { entityType: 'task', operationType: 'task:create', entityId: null, payload: { ...payload } },
                    (operationId) => taskApi.create(payload, operationId),
                );
                // ADR-017 §2.14 — offline: durably queued; it surfaces on drain
                // (no optimistic row) and the shell shows "Waiting to sync".
                if (result.queued) {
                    return null;
                }
                const task = result.value?.task ?? null;
                if (task !== null) {
                    tasks.value = [task, ...tasks.value];
                    return task;
                }
                return null;
            }
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
        const queue = getOfflineQueue();
        try {
            if (queue !== null) {
                const result = await submitOfflineAware<TaskResponse>(
                    queue,
                    { entityType: 'task', operationType: 'task:status', entityId: taskId, payload: { status } },
                    (operationId) => taskApi.setStatus(taskId, status, operationId),
                );
                if (result.queued) {
                    return;
                }
                if (result.value?.task !== undefined) {
                    const task = result.value.task;
                    replaceInList(task);
                    if (current.value?.id === taskId) {
                        current.value = task;
                    }
                    return;
                }
            }
            const { task } = await taskApi.setStatus(taskId, status);
            replaceInList(task);
            if (current.value?.id === taskId) {
                current.value = task;
            }
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    const sacredAnchor = ref<boolean | null>(null);

    async function toggleSacredAnchor(taskId: number, isAnchor: boolean): Promise<void> {
        error.value = null;
        try {
            await taskApi.setSacredAnchor(taskId, isAnchor);
            if (current.value && current.value.id === taskId) {
                current.value = { ...current.value, is_sacred_anchor: isAnchor };
            }
            sacredAnchor.value = isAnchor;
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function setAssignmentLock(taskId: number, locked: boolean): Promise<void> {
        error.value = null;
        try {
            await taskApi.setAssignmentLock(taskId, locked);
            await load(taskId);
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function apiUpdate(taskId: number, payload: UpdateTaskPayload): Promise<void> {
        error.value = null;
        const queue = getOfflineQueue();
        // ADR-017 §2.11 — snapshot the optimistic version for conflict detection.
        const baseVersion = current.value?.id === taskId ? current.value.version : undefined;
        const offlinePayload: UpdateTaskPayload & { base_version?: number } = baseVersion !== undefined
            ? { ...payload, base_version: baseVersion }
            : payload;
        try {
            if (queue !== null) {
                const result = await submitOfflineAware<TaskResponse>(
                    queue,
                    { entityType: 'task', operationType: 'task:update', entityId: taskId, payload: { ...offlinePayload } as Record<string, unknown>, baseVersion },
                    (operationId) => taskApi.update(taskId, offlinePayload, operationId),
                );
                if (result.queued) {
                    return;
                }
                if (result.value?.task !== undefined) {
                    const task = result.value.task;
                    replaceInList(task);
                    if (current.value?.id === taskId) {
                        current.value = task;
                    }
                    return;
                }
            }
            const { task } = await taskApi.update(taskId, offlinePayload);
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
        const queue = getOfflineQueue();
        try {
            if (queue !== null) {
                const result = await submitOfflineAware<SubtaskResponse>(
                    queue,
                    { entityType: 'subtask', operationType: 'subtask:create', entityId: taskId, payload: { title } },
                    (operationId) => taskApi.addSubtask(taskId, title, null, operationId),
                );
                if (result.queued) {
                    return;
                }
                if (result.value?.subtask !== undefined) {
                    subtasks.value = [...subtasks.value, result.value.subtask];
                    return;
                }
            }
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
        assignmentLocked,
        subtasks,
        loading,
        error,
        loadList,
        load,
        create,
        setStatus,
        setAssignmentLock,
        toggleSacredAnchor,
        sacredAnchor,
        apiUpdate,
        addSubtask,
        toggleSubtask,
        partialComplete,
        promoteSubtask,
        clearCurrent,
    };
});
