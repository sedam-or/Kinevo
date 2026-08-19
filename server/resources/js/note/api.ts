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
    list(): Promise<NoteListResponse> {
        return apiClient.request<NoteListResponse>('/notes');
    },

    show(noteId: number): Promise<NoteResponse> {
        return apiClient.request<NoteResponse>(`/notes/${noteId}`);
    },

    create(payload: CreateNotePayload): Promise<NoteResponse> {
        return apiClient.request<NoteResponse>('/notes', { method: 'POST', body: JSON.stringify(payload) });
    },

    update(noteId: number, payload: UpdateNotePayload): Promise<NoteResponse> {
        return apiClient.request<NoteResponse>(`/notes/${noteId}`, { method: 'PATCH', body: JSON.stringify(payload) });
    },

    search(query: string): Promise<SearchResponse> {
        return apiClient.request<SearchResponse>(`/knowledge/search?q=${encodeURIComponent(query)}`);
    },

    links(noteId: number): Promise<NoteLinksResponse> {
        return apiClient.request<NoteLinksResponse>(`/notes/${noteId}/links`);
    },
};
