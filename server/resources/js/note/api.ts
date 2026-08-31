import { apiClient } from '../api/client';
import type {
    CreateNotePayload,
    NoteLinksResponse,
    NoteListResponse,
    NoteResponse,
    SearchResponse,
    UpdateNotePayload,
} from './types';

export const noteApi = {
    list(workspaceId?: number | null): Promise<NoteListResponse> {
        // TASK-P19-014 — declared active workspace; null = global view.
        const query = workspaceId ? `?workspace_id=${workspaceId}` : '';
        return apiClient.request<NoteListResponse>(`/notes${query}`);
    },

    show(noteId: number): Promise<NoteResponse> {
        return apiClient.request<NoteResponse>(`/notes/${noteId}`);
    },

    create(payload: CreateNotePayload, operationId?: string): Promise<NoteResponse> {
        return apiClient.request<NoteResponse>('/notes', {
            method: 'POST',
            body: JSON.stringify(payload),
            ...(operationId ? { headers: { 'X-Operation-Id': operationId } } : {}),
        });
    },

    update(noteId: number, payload: UpdateNotePayload, operationId?: string): Promise<NoteResponse> {
        return apiClient.request<NoteResponse>(`/notes/${noteId}`, {
            method: 'PATCH',
            body: JSON.stringify(payload),
            ...(operationId ? { headers: { 'X-Operation-Id': operationId } } : {}),
        });
    },

    search(query: string): Promise<SearchResponse> {
        return apiClient.request<SearchResponse>(`/knowledge/search?q=${encodeURIComponent(query)}`);
    },

    links(noteId: number): Promise<NoteLinksResponse> {
        return apiClient.request<NoteLinksResponse>(`/notes/${noteId}/links`);
    },
};
