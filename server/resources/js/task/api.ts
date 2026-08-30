import { apiClient } from '../api/client';
import type {
    AssignmentLockResponse,
    CreateTaskPayload,
    PartialCompleteResponse,
    PromoteSubtaskResponse,
    SubtaskListResponse,
    SubtaskResponse,
    TaskDetailResponse,
    TaskListResponse,
    TaskResponse,
    TaskStatusValue,
    ToggleSubtaskResponse,
    UpdateTaskPayload,
} from './types';

export const taskApi = {
    list(workspaceId?: number | null): Promise<TaskListResponse> {
        // TASK-P19-013 — declared active workspace; null = global view.
        const query = workspaceId ? `?workspace_id=${workspaceId}` : '';
        return apiClient.request<TaskListResponse>(`/tasks${query}`);
    },

    create(payload: CreateTaskPayload): Promise<TaskResponse> {
        return apiClient.request<TaskResponse>('/tasks', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    show(taskId: number): Promise<TaskDetailResponse> {
        return apiClient.request<TaskDetailResponse>(`/tasks/${taskId}`);
    },

    update(taskId: number, payload: UpdateTaskPayload): Promise<TaskResponse> {
        return apiClient.request<TaskResponse>(`/tasks/${taskId}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    setStatus(taskId: number, status: TaskStatusValue): Promise<TaskResponse> {
        return apiClient.request<TaskResponse>(`/tasks/${taskId}/status`, {
            method: 'POST',
            body: JSON.stringify({ status }),
        });
    },

    setAssignmentLock(taskId: number, locked: boolean): Promise<AssignmentLockResponse> {
        return apiClient.request<AssignmentLockResponse>(`/tasks/${taskId}/assignment/${locked ? 'lock' : 'unlock'}`, {
            method: 'POST',
        });
    },

    partialComplete(taskId: number): Promise<PartialCompleteResponse> {
        return apiClient.request<PartialCompleteResponse>(`/tasks/${taskId}/partial-complete`, {
            method: 'POST',
        });
    },

    subtasks(taskId: number): Promise<SubtaskListResponse> {
        return apiClient.request<SubtaskListResponse>(`/tasks/${taskId}/subtasks`);
    },

    addSubtask(taskId: number, title: string, notes?: string | null): Promise<SubtaskResponse> {
        return apiClient.request<SubtaskResponse>(`/tasks/${taskId}/subtasks`, {
            method: 'POST',
            body: JSON.stringify({ title, notes: notes ?? null }),
        });
    },

    toggleSubtask(taskId: number, subtaskId: number): Promise<ToggleSubtaskResponse> {
        return apiClient.request<ToggleSubtaskResponse>(`/tasks/${taskId}/subtasks/${subtaskId}/toggle`, {
            method: 'POST',
        });
    },

    promoteSubtask(subtaskId: number): Promise<PromoteSubtaskResponse> {
        return apiClient.request<PromoteSubtaskResponse>(`/subtasks/${subtaskId}/promote`, {
            method: 'POST',
        });
    },
};
