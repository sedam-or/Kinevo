import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
    workspaceApi,
    type CreateWorkspacePayload,
    type UpdateWorkspacePayload,
    type Workspace,
} from './api';
import type { ApiError } from '../api/types';

/**
 * One authoritative active-workspace state (TASK-P19-006).
 *
 * Authority: the SERVER (its default_workspace_id) — the client's stored
 * selection is convenience only and is validated against the loaded list.
 * The selection survives navigation, reload and session restoration through
 * localStorage, and an explicit `?workspace=<id>` deep link wins over both
 * (TASK-P19-007).
 */
const STORAGE_KEY = 'kinevo.active-workspace';

export const useWorkspaceStore = defineStore('workspaces', () => {
    const workspaces = ref<Workspace[]>([]);
    const archived = ref<Workspace[]>([]);
    const serverDefaultId = ref<number | null>(null);
    const activeWorkspaceId = ref<number | null>(null);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    const active = ref(false); // hydrated flag

    const activeWorkspace = computed<Workspace | null>(
        () => workspaces.value.find((w) => w.id === activeWorkspaceId.value)
            ?? workspaces.value.find((w) => w.is_default)
            ?? workspaces.value[0]
            ?? null,
    );

    function readDeepLink(): number | null {
        if (typeof window === 'undefined') return null;
        const raw = new URLSearchParams(window.location.search).get('workspace');
        const id = raw !== null && /^\d+$/.test(raw) ? Number(raw) : null;
        return id !== null && id > 0 ? id : null;
    }

    function readStored(): number | null {
        try {
            const raw = window.localStorage.getItem(STORAGE_KEY);
            const id = raw !== null && /^\d+$/.test(raw) ? Number(raw) : null;
            return id !== null && id > 0 ? id : null;
        } catch {
            return null;
        }
    }

    function persist(id: number | null): void {
        try {
            if (id === null) {
                window.localStorage.removeItem(STORAGE_KEY);
            } else {
                window.localStorage.setItem(STORAGE_KEY, String(id));
            }
        } catch {
            // Storage unavailable (private mode): convenience only.
        }
    }

    async function load(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const response = await workspaceApi.list();
            workspaces.value = response.workspaces.filter((w) => w.status === 'active');
            archived.value = response.workspaces.filter((w) => w.status === 'archived');
            serverDefaultId.value = response.default_workspace_id;

            // Precedence: deep link > stored convenience > server default.
            const candidates = [readDeepLink(), readStored(), response.default_workspace_id];
            for (const candidate of candidates) {
                if (candidate !== null && workspaces.value.some((w) => w.id === candidate)) {
                    activeWorkspaceId.value = candidate;
                    break;
                }
            }
            if (activeWorkspaceId.value === null && activeWorkspace.value !== null) {
                activeWorkspaceId.value = activeWorkspace.value.id;
            }
            persist(activeWorkspaceId.value);
            active.value = true;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    /** Switch the active workspace; survives reload via local persistence. */
    function switchTo(id: number): boolean {
        if (!workspaces.value.some((w) => w.id === id)) {
            // Archived/unknown targets are not switchable (P19-030).
            return false;
        }
        activeWorkspaceId.value = id;
        persist(id);

        return true;
    }

    async function create(payload: CreateWorkspacePayload): Promise<boolean> {
        error.value = null;
        try {
            const { workspace } = await workspaceApi.create(payload);
            workspaces.value.push(workspace);
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    async function update(id: number, payload: UpdateWorkspacePayload): Promise<boolean> {
        error.value = null;
        try {
            const { workspace } = await workspaceApi.update(id, payload);
            const index = workspaces.value.findIndex((w) => w.id === id);
            if (index >= 0) workspaces.value[index] = workspace;
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    async function archive(id: number): Promise<boolean> {
        error.value = null;
        try {
            await workspaceApi.archive(id);
            const [removed] = workspaces.value.splice(workspaces.value.findIndex((w) => w.id === id), 1);
            if (removed) archived.value.push({ ...removed, status: 'archived' });
            // Falling back to another workspace mirrors the server contract:
            // archived workspaces leave the active switcher (P19-030).
            if (activeWorkspaceId.value === id) {
                const fallback = workspaces.value.find((w) => w.is_default) ?? workspaces.value[0] ?? null;
                activeWorkspaceId.value = fallback?.id ?? null;
                persist(activeWorkspaceId.value);
            }
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    async function restore(id: number): Promise<boolean> {
        error.value = null;
        try {
            await workspaceApi.restore(id);
            const index = archived.value.findIndex((w) => w.id === id);
            if (index >= 0) {
                const [restored] = archived.value.splice(index, 1);
                if (restored) workspaces.value.push({ ...restored, status: 'active' });
            }
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    async function setDefault(id: number): Promise<boolean> {
        error.value = null;
        try {
            await workspaceApi.setDefault(id);
            for (const w of workspaces.value) {
                w.is_default = w.id === id;
            }
            serverDefaultId.value = id;

            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    return {
        workspaces, archived, serverDefaultId, activeWorkspaceId, activeWorkspace,
        loading, error, active,
        load, switchTo, create, update, archive, restore, setDefault,
    };
});
