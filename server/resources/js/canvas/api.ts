import { apiClient } from '../api/client';
import type {
    CanvasCreatePayload,
    CanvasDocumentResponse,
    CanvasListResponse,
    CanvasResponse,
    CanvasSavePayload,
    CanvasWithDocumentResponse,
} from './api-types';

export const canvasApi = {
    list(): Promise<CanvasListResponse> {
        return apiClient.request<CanvasListResponse>('/canvases');
    },

    show(canvasId: number): Promise<CanvasWithDocumentResponse> {
        return apiClient.request<CanvasWithDocumentResponse>(`/canvases/${canvasId}`);
    },

    create(payload: CanvasCreatePayload): Promise<CanvasResponse> {
        return apiClient.request<CanvasResponse>('/canvases', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    save(canvasId: number, payload: CanvasSavePayload): Promise<CanvasDocumentResponse> {
        return apiClient.request<CanvasDocumentResponse>(`/canvases/${canvasId}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    rename(canvasId: number, title: string): Promise<CanvasResponse> {
        return apiClient.request<CanvasResponse>(`/canvases/${canvasId}`, {
            method: 'PATCH',
            body: JSON.stringify({ title }),
        });
    },

    archive(canvasId: number): Promise<CanvasResponse> {
        return apiClient.request<CanvasResponse>(`/canvases/${canvasId}/archive`, {
            method: 'POST',
        });
    },
};