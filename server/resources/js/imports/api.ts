import { apiClient } from '../api/client';
import type { IcsImportResponse, KrsImportResponse } from './types';

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

    uploadIcs(file: File): Promise<IcsImportResponse> {
        const form = new FormData();
        form.append('file', file);
        return apiClient.request<IcsImportResponse>('/imports/ics', {
            method: 'POST',
            body: form,
            noRetry: true,
        });
    },

    getIcs(importId: number): Promise<IcsImportResponse> {
        return apiClient.request<IcsImportResponse>(`/imports/ics/${importId}`);
    },

    confirmIcs(importId: number): Promise<IcsImportResponse> {
        return apiClient.request<IcsImportResponse>(`/imports/ics/${importId}/confirm`, {
            method: 'POST',
            noRetry: true,
        });
    },

    discardIcs(importId: number): Promise<IcsImportResponse> {
        return apiClient.request<IcsImportResponse>(`/imports/ics/${importId}/discard`, {
            method: 'POST',
            noRetry: true,
        });
    },
};