import { defineStore } from 'pinia';
import { useWorkspaceStore } from '../workspace/store';
import { ref } from 'vue';
import { noteApi } from './api';
import { useApiStore } from '../api/store';
import type { ApiError } from '../api/types';
import type { Note, NoteLink } from './types';

export type NoteSaveState = 'saved' | 'saving' | 'error' | 'offline' | 'conflict';

export const useNoteStore = defineStore('note', () => {
    const api = useApiStore();

    const notes = ref<Note[]>([]);
    const searchResults = ref<Note[]>([]);
    const current = ref<Note | null>(null);
    const links = ref<NoteLink[]>([]);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);
    const saveState = ref<NoteSaveState>('saved');

    async function loadList(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            // TASK-P19-014 — scope to the declared active workspace.
            const wid = useWorkspaceStore().activeWorkspaceId;
            const { notes: list } = await noteApi.list(wid);
            notes.value = list;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function search(query: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const { notes: results } = await noteApi.search(query);
            searchResults.value = results;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function load(noteId: number): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const { note } = await noteApi.show(noteId);
            current.value = note;
            const { links: linked } = await noteApi.links(noteId);
            links.value = linked;
            saveState.value = 'saved';
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function create(title: string): Promise<Note | null> {
        error.value = null;
        try {
            const { note } = await noteApi.create({ title });
            notes.value = [note, ...notes.value];
            return note;
        } catch (err) {
            error.value = err as ApiError;
            return null;
        }
    }

    async function save(payload: { title?: string; plain_text_cache?: string | null; markdown_cache?: string | null; document_json?: Record<string, unknown> | null }): Promise<void> {
        if (!current.value) {
            return;
        }

        if (!api.online) {
            saveState.value = 'offline';
            return;
        }

        saveState.value = 'saving';
        error.value = null;
        try {
            const { note } = await noteApi.update(current.value.id, {
                title: payload.title,
                plain_text_cache: payload.plain_text_cache ?? null,
                markdown_cache: payload.markdown_cache ?? null,
                document_json: payload.document_json ?? null,
                base_version: current.value.version,
            });
            current.value = note;
            saveState.value = 'saved';
        } catch (err) {
            const apiError = err as ApiError;
            error.value = apiError;
            saveState.value = apiError.status === 409 ? 'conflict' : 'error';
        }
    }

    return {
        notes,
        searchResults,
        current,
        links,
        loading,
        error,
        saveState,
        loadList,
        search,
        load,
        create,
        save,
    };
});
