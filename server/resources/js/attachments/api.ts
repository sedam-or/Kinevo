import { apiClient } from '../api/client';
import { readToken } from '../api/token';
import type { AttachmentListResponse, AttachmentResponse, AttachmentRules } from './types';

const API_BASE = '/api/v1';

export const attachmentApi = {
    rules(): Promise<AttachmentRules> {
        return apiClient.request<AttachmentRules>('/attachments/rules');
    },

    list(taskId: number): Promise<AttachmentListResponse> {
        return apiClient.request<AttachmentListResponse>(`/tasks/${taskId}/attachments`);
    },

    upload(taskId: number, file: File): Promise<AttachmentResponse> {
        const form = new FormData();
        form.append('file', file);
        return apiClient.request<AttachmentResponse>(`/tasks/${taskId}/attachments`, {
            method: 'POST',
            body: form,
            noRetry: true,
        });
    },

    remove(taskId: number, attachmentId: number): Promise<{ deleted: boolean }> {
        return apiClient.request<{ deleted: boolean }>(`/tasks/${taskId}/attachments/${attachmentId}`, {
            method: 'DELETE',
            noRetry: true,
        });
    },

    async download(taskId: number, attachmentId: number, filename: string): Promise<void> {
        const token = readToken();
        const headers: Record<string, string> = {};
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }
        const response = await fetch(`${API_BASE}/tasks/${taskId}/attachments/${attachmentId}`, { headers });

        if (!response.ok) {
            throw new Error('Download failed.');
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = filename;
        anchor.click();
        URL.revokeObjectURL(url);
    },
};