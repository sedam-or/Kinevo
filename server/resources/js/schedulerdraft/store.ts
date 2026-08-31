import { defineStore } from 'pinia';
import { ref } from 'vue';
import { scheduleDraftApi } from './api';
import type { ApiError } from '../api/types';
import type {
    DraftApplyResponse,
    DraftResponse,
    RescheduleApplyResponse,
    RescheduleProposal,
    RescheduleResponse,
    ScheduleDraft,
    ScheduleDraftRecord,
    SyncNowResponse,
} from './types';

export const useScheduleDraftStore = defineStore('scheduleDraft', () => {
    const busy = ref(false);
    const error = ref<ApiError | null>(null);

    const draft = ref<ScheduleDraft | null>(null);
    const draftBaseVersion = ref<number | null>(null);
    const draftApplyResult = ref<DraftApplyResponse | null>(null);

    const proposal = ref<RescheduleProposal | null>(null);
    const proposalHasChanges = ref(false);
    const rescheduleApplyResult = ref<RescheduleApplyResponse | null>(null);

    // ADR-016 — Sync Now + persisted weekly draft lifecycle.
    const syncStatus = ref<SyncNowResponse['status'] | null>(null);
    const syncNeedsReview = ref(false);
    const weeklyDrafts = ref<ScheduleDraftRecord[]>([]);
    const weeklyDraftMessage = ref<string | null>(null);

    async function generate(from: string, to: string): Promise<DraftResponse | null> {
        busy.value = true;
        error.value = null;
        draft.value = null;
        draftApplyResult.value = null;
        try {
            const response = await scheduleDraftApi.generate(from, to);
            draft.value = response.draft;
            draftBaseVersion.value = response.base_version;
            return response;
        } catch (err) {
            error.value = err as ApiError;
            return null;
        } finally {
            busy.value = false;
        }
    }

    async function applyDraft(): Promise<boolean> {
        if (!draft.value || draftBaseVersion.value === null) {
            return false;
        }
        busy.value = true;
        error.value = null;
        try {
            draftApplyResult.value = await scheduleDraftApi.applyDraft(draft.value, draftBaseVersion.value);
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        } finally {
            busy.value = false;
        }
    }

    async function propose(from: string, to: string): Promise<RescheduleResponse | null> {
        busy.value = true;
        error.value = null;
        proposal.value = null;
        rescheduleApplyResult.value = null;
        try {
            const response = await scheduleDraftApi.propose(from, to);
            proposal.value = response.proposal;
            proposalHasChanges.value = response.has_changes;
            return response;
        } catch (err) {
            error.value = err as ApiError;
            return null;
        } finally {
            busy.value = false;
        }
    }

    async function applyProposal(): Promise<boolean> {
        if (!proposal.value) {
            return false;
        }
        busy.value = true;
        error.value = null;
        try {
            rescheduleApplyResult.value = await scheduleDraftApi.applyProposal(proposal.value, proposal.value.base_version);
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        } finally {
            busy.value = false;
        }
    }

    function clearDraft(): void {
        draft.value = null;
        draftBaseVersion.value = null;
        draftApplyResult.value = null;
    }

    function clearProposal(): void {
        proposal.value = null;
        proposalHasChanges.value = false;
        rescheduleApplyResult.value = null;
    }

    // --- ADR-016: Sync Now (never applies; diff reviewed via applyProposal) ---

    async function sync(): Promise<SyncNowResponse | null> {
        busy.value = true;
        error.value = null;
        clearProposal();
        try {
            const response = await scheduleDraftApi.sync();
            syncStatus.value = response.status;
            syncNeedsReview.value = response.needs_review;
            if (response.status === 'proposal' && response.proposal !== null) {
                proposal.value = response.proposal;
                proposalHasChanges.value = response.proposal.moves.length > 0;
            }
            return response;
        } catch (err) {
            error.value = err as ApiError;
            return null;
        } finally {
            busy.value = false;
        }
    }

    // --- ADR-016: persisted weekly drafts ---

    async function loadWeeklyDrafts(): Promise<void> {
        error.value = null;
        try {
            const response = await scheduleDraftApi.listDrafts();
            weeklyDrafts.value = response.drafts;
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function applyWeeklyDraft(record: ScheduleDraftRecord): Promise<boolean> {
        busy.value = true;
        error.value = null;
        try {
            draftApplyResult.value = await scheduleDraftApi.applyDraft(
                record.payload.draft,
                record.payload.base_version,
                record.id,
            );
            weeklyDraftMessage.value = draftApplyResult.value?.applied
                ? `Weekly draft applied at schedule version ${draftApplyResult.value.version}.`
                : 'Weekly draft was already applied (no changes).';
            weeklyDrafts.value = weeklyDrafts.value.filter((d) => d.id !== record.id);
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        } finally {
            busy.value = false;
        }
    }

    async function discardWeeklyDraft(record: ScheduleDraftRecord): Promise<boolean> {
        busy.value = true;
        error.value = null;
        try {
            await scheduleDraftApi.discardDraft(record.id);
            weeklyDrafts.value = weeklyDrafts.value.filter((d) => d.id !== record.id);
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        } finally {
            busy.value = false;
        }
    }

    return {
        busy,
        error,
        draft,
        draftBaseVersion,
        draftApplyResult,
        proposal,
        proposalHasChanges,
        rescheduleApplyResult,
        generate,
        applyDraft,
        propose,
        applyProposal,
        clearDraft,
        clearProposal,
        syncStatus,
        syncNeedsReview,
        weeklyDrafts,
        weeklyDraftMessage,
        sync,
        loadWeeklyDrafts,
        applyWeeklyDraft,
        discardWeeklyDraft,
    };
});
