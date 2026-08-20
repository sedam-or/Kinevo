import { apiClient } from '../api/client';
import type {
    ExecutionAbandonResponse,
    ExecutionActiveResponse,
    ExecutionCompleteResponse,
    ExecutionListResponse,
    ExecutionPauseResponse,
    ExecutionResumeResponse,
    ExecutionStartResponse,
} from './types';

export const executionApi = {
    active(): Promise<ExecutionActiveResponse> {
        return apiClient.request<ExecutionActiveResponse>('/execution/active');
    },

    list(taskId?: number): Promise<ExecutionListResponse> {
        const query = taskId !== undefined ? `?task_id=${encodeURIComponent(taskId)}` : '';
        return apiClient.request<ExecutionListResponse>(`/execution${query}`);
    },

    start(taskId: number): Promise<ExecutionStartResponse> {
        return apiClient.request<ExecutionStartResponse>('/execution/start', {
            method: 'POST',
            body: JSON.stringify({ task_id: taskId }),
            noRetry: true,
        });
    },

    pause(sessionId: number): Promise<ExecutionPauseResponse> {
        return apiClient.request<ExecutionPauseResponse>(`/execution/${sessionId}/pause`, {
            method: 'POST',
            noRetry: true,
        });
    },

    resume(sessionId: number): Promise<ExecutionResumeResponse> {
        return apiClient.request<ExecutionResumeResponse>(`/execution/${sessionId}/resume`, {
            method: 'POST',
            noRetry: true,
        });
    },

    complete(sessionId: number): Promise<ExecutionCompleteResponse> {
        return apiClient.request<ExecutionCompleteResponse>(`/execution/${sessionId}/complete`, {
            method: 'POST',
            noRetry: true,
        });
    },

    abandon(sessionId: number): Promise<ExecutionAbandonResponse> {
        return apiClient.request<ExecutionAbandonResponse>(`/execution/${sessionId}/abandon`, {
            method: 'POST',
            noRetry: true,
        });
    },
};