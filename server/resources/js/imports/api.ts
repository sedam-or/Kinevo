import { apiClient } from '../api/client';
import type { KrsImportResponse } from './types';

export const importApi = {
    uploadKrs(file: File): Promise<KrsImportResponse> {
        const form = new FormData();
        form.append('file', file);
        return apiClient.request<KrsImportResponse>('/imports/krs-pdf', {
            method: 'POST',
            body: form,
            noRetry: true,
        });
    },

    get(importId: number): Promise<KrsImportResponse> {
        return apiClient.request<KrsImportResponse>(`/imports/${importId}`);
    },

    confirm(importId: number): Promise<KrsImportResponse> {
        return apiClient.request<KrsImportResponse>(`/imports/${importId}/confirm`, {
            method: 'POST',
            noRetry: true,
        });
    },

    discard(importId: number): Promise<KrsImportResponse> {
        return apiClient.request<KrsImportResponse>(`/imports/${importId}/discard`, {
            method: 'POST',
            noRetry: true,
        });
    },
};