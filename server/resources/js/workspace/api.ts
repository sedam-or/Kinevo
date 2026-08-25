import { apiClient } from '../api/client';

/** A workspace is a top-level context container (TASK-P19-001). */
export interface Workspace {
    id: number;
    user_id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string | null;
    accent: string | null;
    type: 'personal' | 'work' | 'research' | 'learning' | 'other';
    is_default: boolean;
    status: 'active' | 'archived';
}

export interface WorkspaceListResponse {
    workspaces: Workspace[];
    default_workspace_id: number | null;
}

export type CreateWorkspacePayload = {
    name: string;
    description?: string | null;
    icon?: string | null;
    accent?: string | null;
    type?: string;
};

export type UpdateWorkspacePayload = Partial<Omit<CreateWorkspacePayload, 'type'>> & {
    type?: string;
};

export const workspaceApi = {
    async list(includeArchived = false): Promise<WorkspaceListResponse> {
        const query = includeArchived ? '?include_archived=1' : '';
        return apiClient.request<WorkspaceListResponse>(`/workspaces${query}`);
    },

    async create(payload: CreateWorkspacePayload): Promise<{ workspace: Workspace }> {
        return apiClient.request<{ workspace: Workspace }>('/workspaces', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    async update(id: number, payload: UpdateWorkspacePayload): Promise<{ workspace: Workspace }> {
        return apiClient.request<{ workspace: Workspace }>(`/workspaces/${id}`, {
            method: 'PATCH',
            body: JSON.stringify(payload),
        });
    },

    async archive(id: number): Promise<{ workspace: Workspace }> {
        return apiClient.request<{ workspace: Workspace }>(`/workspaces/${id}/archive`, { method: 'DELETE' });
    },

    async restore(id: number): Promise<{ workspace: Workspace }> {
        return apiClient.request<{ workspace: Workspace }>(`/workspaces/${id}/restore`, { method: 'POST' });
    },

    async setDefault(id: number): Promise<{ workspace: Workspace }> {
        return apiClient.request<{ workspace: Workspace }>(`/workspaces/${id}/default`, { method: 'POST' });
    },
};
