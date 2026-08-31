import { apiClient } from '../api/client';
import type {
    DraftApplyResponse,
    DraftResponse,
    RescheduleApplyResponse,
    RescheduleProposal,
    RescheduleResponse,
    ScheduleDraft,
    ScheduleDraftsResponse,
    SyncNowResponse,
} from './types';

export const scheduleDraftApi = {
    generate(from: string, to: string): Promise<DraftResponse> {
        return apiClient.request<DraftResponse>('/schedule/draft', {
            method: 'POST',
            body: JSON.stringify({ from, to }),
        });
    },

    applyDraft(draft: ScheduleDraft, baseVersion: number, draftId?: number): Promise<DraftApplyResponse> {
        return apiClient.request<DraftApplyResponse>('/schedule/draft/apply', {
            method: 'POST',
            body: JSON.stringify({ draft, base_version: baseVersion, ...(draftId ? { draft_id: draftId } : {}) }),
        });
    },

    propose(from: string, to: string): Promise<RescheduleResponse> {
        return apiClient.request<RescheduleResponse>('/schedule/reschedule', {
            method: 'POST',
            body: JSON.stringify({ from, to }),
        });
    },

    applyProposal(proposal: RescheduleProposal, baseVersion: number): Promise<RescheduleApplyResponse> {
        return apiClient.request<RescheduleApplyResponse>('/schedule/reschedule/apply', {
            method: 'POST',
            body: JSON.stringify({ proposal, base_version: baseVersion }),
        });
    },

    sync(from?: string, to?: string): Promise<SyncNowResponse> {
        return apiClient.request<SyncNowResponse>('/schedule/sync', {
            method: 'POST',
            body: JSON.stringify({ ...(from && to ? { from, to } : {}) }),
        });
    },

    listDrafts(): Promise<ScheduleDraftsResponse> {
        return apiClient.request<ScheduleDraftsResponse>('/schedule/drafts', { method: 'GET' });
    },

    discardDraft(draftId: number): Promise<{ discarded: boolean }> {
        return apiClient.request<{ discarded: boolean }>(`/schedule/drafts/${draftId}/discard`, {
            method: 'POST',
        });
    },
};
