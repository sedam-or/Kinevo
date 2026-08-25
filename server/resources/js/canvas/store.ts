import { defineStore } from 'pinia';
import { useWorkspaceStore } from '../workspace/store';
import { ref } from 'vue';
import { canvasApi } from './api';
import type { ApiError } from '../api/types';
import type { CanvasDto } from './api-types';

export type CanvasSaveState = 'saved' | 'saving' | 'error' | 'offline' | 'conflict';

export const useCanvasStore = defineStore('canvas', () => {
    const canvases = ref<CanvasDto[]>([]);
    const current = ref<CanvasDto | null>(null);
    const document = ref<Record<string, unknown> | null>(null);
    const documentVersion = ref(0);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);
    const saveState = ref<CanvasSaveState>('saved');

    async function loadList(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            // TASK-P19-017 — scope to the declared active workspace.
            const wid = useWorkspaceStore().activeWorkspaceId;
            const { canvases: list } = await canvasApi.list(wid);
            canvases.value = list;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function open(canvasId: number): Promise<void> {
        loading.value = true;
        error.value = null;
        saveState.value = 'saved';
        try {
            const { canvas, document: doc } = await canvasApi.show(canvasId);
            current.value = canvas;
            if (doc !== null) {
                document.value = doc.scene_json;
                documentVersion.value = doc.version;
            } else {
                document.value = null;
                documentVersion.value = 0;
            }
        } catch (err) {
            error.value = err as ApiError;
            current.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function create(title: string): Promise<CanvasDto | null> {
        error.value = null;
        try {
            const { canvas } = await canvasApi.create({ title });
            canvases.value = [canvas, ...canvases.value];
            return canvas;
        } catch (err) {
            error.value = err as ApiError;
            return null;
        }
    }

    async function rename(title: string): Promise<boolean> {
        if (!current.value) {
            return false;
        }
        error.value = null;
        try {
            const { canvas } = await canvasApi.rename(current.value.id, title);
            current.value = canvas;
            const index = canvases.value.findIndex((c) => c.id === canvas.id);
            if (index !== -1) {
                canvases.value[index] = canvas;
            }
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    async function archive(): Promise<boolean> {
        if (!current.value) {
            return false;
        }
        error.value = null;
        try {
            await canvasApi.archive(current.value.id);
            canvases.value = canvases.value.filter((c) => c.id !== current.value?.id);
            current.value = null;
            document.value = null;
            documentVersion.value = 0;
            saveState.value = 'saved';
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    /** Called by the workspace after a successful autosave. */
    function setSaveState(state: CanvasSaveState): void {
        saveState.value = state;
    }

    /** Called by the workspace autosave controller on a successful save. */
    function recordSaved(scene: Record<string, unknown>, version: number): void {
        document.value = scene;
        documentVersion.value = version;
        saveState.value = 'saved';
    }

    /** Adopt the server copy after a conflict (reconcile). */
    function reconcile(scene: Record<string, unknown>, version: number): void {
        document.value = scene;
        documentVersion.value = version;
        saveState.value = 'saved';
    }

    function clear(): void {
        current.value = null;
        document.value = null;
        documentVersion.value = 0;
        saveState.value = 'saved';
        error.value = null;
    }

    return {
        canvases,
        current,
        document,
        documentVersion,
        loading,
        error,
        saveState,
        loadList,
        open,
        create,
        rename,
        archive,
        setSaveState,
        recordSaved,
        reconcile,
        clear,
    };
});