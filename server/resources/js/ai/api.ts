import { apiClient } from '../api/client';

/**
 * Canonical AI status states (TASK-P17-007) — server-derived via
 * GET /api/v1/ai/status; the UI MUST render from this union, never re-guess
 * from raw fields. `testing` is client-transient during a connection test.
 */
export type AiStatusState =
    | 'disabled'
    | 'not_configured'
    | 'configured'
    | 'testing'
    | 'connected'
    | 'degraded'
    | 'unavailable';

export interface AiProviderStatus {
    provider: string;
    model: string;
    available: boolean;
    latency_ms: number | null;
    error: string | null;
}

export interface AiProviderConfigPayload {
    provider: string;
    enabled: boolean;
    model: string | null;
    base_url: string | null;
    has_api_key: boolean;
    api_key_hint: string | null;
    status: AiProviderStatus & { state: AiStatusState };
    privacy_ok: boolean;
}

export interface SaveAiProviderConfigInput {
    provider: string;
    enabled?: boolean;
    model?: string | null;
    base_url?: string | null;
    api_key?: string | null;
    remove_api_key?: boolean;
}

export interface BreakdownMilestone {
    title: string;
    target_date?: string | null;
    estimated_minutes?: number | null;
}

export interface AiProposalPayload {
    type: 'goal_breakdown_proposal';
    goal_id: number;
    rationale?: string;
    assumptions?: string[];
    inputs?: string[];
    constraints?: string[];
    risks?: string[];
    milestones: BreakdownMilestone[];
}

export interface AiProposal {
    id: number;
    user_id: number;
    proposal_type: string;
    schema_version: number;
    payload: AiProposalPayload;
    decision: string;
    operation_id: string | null;
    created_at: string;
}

export const aiApi = {
    async config(): Promise<{ config: AiProviderConfigPayload }> {
        return apiClient.request<{ config: AiProviderConfigPayload }>('/ai/config');
    },

    async save(input: SaveAiProviderConfigInput): Promise<{ config: AiProviderConfigPayload }> {
        return apiClient.request<{ config: AiProviderConfigPayload }>('/ai/config', {
            method: 'PUT',
            body: JSON.stringify(input),
        });
    },

    async test(input: SaveAiProviderConfigInput): Promise<{ status: AiProviderStatus }> {
        return apiClient.request<{ status: AiProviderStatus }>('/ai/config/test', {
            method: 'POST',
            body: JSON.stringify(input),
        });
    },

    async proposals(goalId: number, decision = 'pending'): Promise<{ proposals: AiProposal[] }> {
        return apiClient.request<{ proposals: AiProposal[] }>(
            `/ai/proposals?proposal_type=goal_breakdown&decision=${encodeURIComponent(decision)}&limit=20`,
        ).then(async (page) => ({
            proposals: page.proposals.filter((p) => p.payload?.goal_id === goalId),
        }));
    },

    async updateProposal(proposalId: number, payload: AiProposalPayload): Promise<{ proposal: AiProposal }> {
        return apiClient.request<{ proposal: AiProposal }>(`/ai/proposals/${proposalId}`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },

    async acceptProposal(proposalId: number): Promise<void> {
        await apiClient.request<unknown>(`/ai/proposals/${proposalId}/accept`, { method: 'POST' });
    },

    async rejectProposal(proposalId: number): Promise<void> {
        await apiClient.request<unknown>(`/ai/proposals/${proposalId}/reject`, { method: 'POST' });
    },
};