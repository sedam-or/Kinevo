import { apiClient } from '../api/client';
import type {
    DraftApplyResponse,
    DraftResponse,
    RescheduleApplyResponse,
    RescheduleProposal,
    RescheduleResponse,
    ScheduleDraft,
} from './types';

export const scheduleDraftApi = {
    generate(from: string, to: string): Promise<DraftResponse> {
        return apiClient.request<DraftResponse>('/schedule/draft', {
            method: 'POST',
            body: JSON.stringify({ from, to }),
        });
    },

    applyDraft(draft: ScheduleDraft, baseVersion: number): Promise<DraftApplyResponse> {
        return apiClient.request<DraftApplyResponse>('/schedule/draft/apply', {
            method: 'POST',
            body: JSON.stringify({ draft, base_version: baseVersion }),
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
};
