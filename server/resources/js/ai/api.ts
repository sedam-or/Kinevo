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
    protocol: string;
    enabled: boolean;
    model: string | null;
    base_url: string | null;
    configured: boolean;
    has_api_key: boolean;
    api_key_hint: string | null;
    last_verified_at: string | null;
    last_status: 'connected' | 'failed' | null;
    last_error_code: string | null;
    status: AiProviderStatus & { state: AiStatusState };
    privacy_ok: boolean;
}

/** Provider capability fact (TASK-P18-012) — the UI derives fields from this. */
export interface AiProviderCatalogEntry {
    id: string;
    protocols: string[];
    default_protocol: string;
    requires_api_key: boolean;
    requires_base_url: boolean;
    requires_model: boolean;
    supports_local: boolean;
    supports_remote: boolean;
    supports_connection_test: boolean;
}

/** Stable connection-test outcome (TASK-P18-008) — safe codes only. */
export interface AiConnectionTestResult {
    status: AiProviderStatus;
    ok: boolean;
    code: string | null;
    message: string;
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

export interface ExtractedTask {
    title: string;
    due_at?: string | null;
    estimated_minutes?: number | null;
}

/**
 * Proposal payload union (TASK-P17-029): every variant is server-validated
 * against its versioned schema (FR-61) before it is ever persisted, and each
 * mutating kind is applied only through the accept endpoint (FR-62).
 */
export type AiProposalPayload =
    | {
        type: 'goal_breakdown_proposal';
        goal_id: number;
        rationale?: string;
        assumptions?: string[];
        inputs?: string[];
        constraints?: string[];
        risks?: string[];
        milestones: BreakdownMilestone[];
    }
    | { type: 'summary_proposal'; summary: string; key_points: string[] }
    | { type: 'task_extraction_proposal'; tasks: ExtractedTask[] }
    | { type: 'canvas_proposal'; title: string; sections: { name: string; description?: string }[] };

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

export interface AiGeneratedText {
    text: string;
    provider: string;
    model: string;
    latency_ms: number | null;
}

/**
 * Accept result (TASK-P17-029): the endpoint dispatches on the stored
 * proposal type — milestones for goal breakdowns, tasks for note extractions,
 * a canvas document for canvas proposals.
 */
export interface AiAcceptResult {
    milestones?: Array<{ id: number; title: string }>;
    tasks?: Array<{ id: number; title: string }>;
    canvas?: { id: number; title: string } | null;
}

/** TASK-P25-010 — user cost-alert event (in-app, unread until dismissed). */
export interface AiUsageAlert {
    id: number;
    kind: string;
    threshold: number | null;
    context: Record<string, unknown>;
    seen_at: string | null;
    created_at: string;
}

/** TASK-P25-009 — AI run audit metadata (safe projection, no prompt content). */
export interface AiRunRecord {
    id: number;
    request_id: string | null;
    provider: string;
    model: string;
    proposal_type: string;
    input_tokens: number | null;
    output_tokens: number | null;
    credits_consumed: number;
    estimated_cost_minor: number | null;
    cost_currency: string | null;
    billing_ledger: string;
    status: string;
    latency_ms: number | null;
    created_at: string;
}

export interface AiUsageBreakdownEntry {
    type: string;
    count: number;
    kinevo_cost_minor: number;
}

/** TASK-P25-009 — Settings → AI Usage summary (summary-first, no charts). */
export interface AiUsageSummary {
    period: string;
    period_start: string;
    period_end: string;
    plan: { code: string; name: string };
    credits: { used: number; limit: number; remaining: number; percent: number };
    kinevo: { request_count: number; estimated_cost_minor: number; currency: string };
    byok: { request_count: number };
    breakdown: AiUsageBreakdownEntry[];
    alerts: { unread_count: number; items: AiUsageAlert[] };
}

export const aiApi = {
    async config(): Promise<{ config: AiProviderConfigPayload }> {
        return apiClient.request<{ config: AiProviderConfigPayload }>('/ai/settings');
    },

    async save(input: SaveAiProviderConfigInput): Promise<{ config: AiProviderConfigPayload }> {
        return apiClient.request<{ config: AiProviderConfigPayload }>('/ai/settings', {
            method: 'PATCH',
            body: JSON.stringify(input),
        });
    },

    /** Credential-only rotation (TASK-P18-022) — never returns the secret. */
    async setCredential(apiKey: string): Promise<{ config: AiProviderConfigPayload }> {
        return apiClient.request<{ config: AiProviderConfigPayload }>('/ai/settings/credential', {
            method: 'POST',
            body: JSON.stringify({ api_key: apiKey }),
        });
    },

    async removeCredential(): Promise<{ config: AiProviderConfigPayload }> {
        return apiClient.request<{ config: AiProviderConfigPayload }>('/ai/settings/credential', {
            method: 'DELETE',
        });
    },

    async enable(): Promise<{ config: AiProviderConfigPayload }> {
        return apiClient.request<{ config: AiProviderConfigPayload }>('/ai/settings/enable', { method: 'POST' });
    },

    async disable(): Promise<{ config: AiProviderConfigPayload }> {
        return apiClient.request<{ config: AiProviderConfigPayload }>('/ai/settings/disable', { method: 'POST' });
    },

    async providers(): Promise<{ providers: AiProviderCatalogEntry[] }> {
        return apiClient.request<{ providers: AiProviderCatalogEntry[] }>('/ai/providers');
    },

    async test(input: Partial<SaveAiProviderConfigInput>): Promise<AiConnectionTestResult> {
        return apiClient.request<AiConnectionTestResult>('/ai/settings/test', {
            method: 'POST',
            body: JSON.stringify(input),
        });
    },

    async proposals(goalId: number, decision = 'pending'): Promise<{ proposals: AiProposal[] }> {
        return apiClient.request<{ proposals: AiProposal[] }>(
            `/ai/proposals?proposal_type=goal_breakdown&decision=${encodeURIComponent(decision)}&limit=20`,
        ).then(async (page) => ({
            proposals: page.proposals.filter((p) => p.payload.type === 'goal_breakdown_proposal' && p.payload.goal_id === goalId),
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

    /**
     * Contextual AI entry points (TASK-P17-029, FR-60). Each returns the
     * pending proposal for inline review; nothing is applied until the user
     * accepts through acceptProposalWithResult (FR-62).
     */
    async summarizeNote(noteId: number): Promise<{ proposal: AiProposal }> {
        return apiClient.request<{ proposal: AiProposal }>('/ai/summarize-note', {
            method: 'POST',
            body: JSON.stringify({ note_id: noteId }),
        });
    },

    async extractTasks(noteId: number): Promise<{ proposal: AiProposal }> {
        return apiClient.request<{ proposal: AiProposal }>('/ai/extract-tasks', {
            method: 'POST',
            body: JSON.stringify({ note_id: noteId }),
        });
    },

    async suggestCanvas(prompt: string): Promise<{ proposal: AiProposal }> {
        return apiClient.request<{ proposal: AiProposal }>('/ai/suggest-canvas', {
            method: 'POST',
            body: JSON.stringify({ prompt }),
        });
    },

    /** Non-mutating text generation (e.g. task clarification). */
    async generateText(role: string, prompt: string, systemPrompt?: string): Promise<AiGeneratedText> {
        return apiClient.request<AiGeneratedText>('/ai/generate', {
            method: 'POST',
            body: JSON.stringify({
                role,
                prompt,
                ...(systemPrompt ? { system_prompt: systemPrompt } : {}),
            }),
        });
    },

    async acceptProposalWithResult(proposalId: number): Promise<AiAcceptResult> {
        return apiClient.request<AiAcceptResult>(`/ai/proposals/${proposalId}/accept`, { method: 'POST' });
    },

    /** TASK-P25-009 — AI Usage summary for the Settings→AI Usage surface. */
    async usage(): Promise<AiUsageSummary> {
        return apiClient.request<AiUsageSummary>('/ai/usage');
    },

    /** TASK-P25-010 — unread user cost alerts. */
    async alerts(): Promise<{ alerts: AiUsageAlert[] }> {
        return apiClient.request<{ alerts: AiUsageAlert[] }>('/ai/alerts');
    },

    /** TASK-P25-010 — dismiss all unread user cost alerts. */
    async readAlerts(): Promise<{ marked_read: number }> {
        return apiClient.request<{ marked_read: number }>('/ai/alerts/read', { method: 'POST' });
    },

    /** TASK-P25-009 — recent AI run audit records (safe metadata only). */
    async runs(limit = 10): Promise<{ runs: AiRunRecord[] }> {
        return apiClient.request<{ runs: AiRunRecord[] }>(`/ai/runs?limit=${limit}`);
    },
};