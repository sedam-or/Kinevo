import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        scheduleDraftApi: {
            generate: vi.fn(),
            applyDraft: vi.fn(),
            propose: vi.fn(),
            applyProposal: vi.fn(),
        },
    };
});

import { useScheduleDraftStore } from '../store';
import { scheduleDraftApi } from '../api';
import type { DraftResponse, RescheduleResponse } from '../types';

const draftResponse: DraftResponse = {
    draft: {
        assignments: [{ task_id: '1', title: 'Write', start: '2026-08-17T09:00:00', end: '2026-08-17T10:00:00' }],
        unassigned: [{ task_id: '2', title: 'Call', reason: 'NO_AVAILABLE_SLOT' }],
    },
    base_version: 5,
};

const proposalResponse: RescheduleResponse = {
    proposal: {
        base_version: 5,
        new_version: 6,
        moves: [
            {
                task_id: '1',
                title: 'Write',
                from: { start: '2026-08-17T09:00:00', end: '2026-08-17T10:00:00' },
                to: { start: '2026-08-18T09:00:00', end: '2026-08-18T10:00:00' },
            },
        ],
        conflict_task_ids: ['3'],
    },
    has_changes: true,
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('schedule draft store', () => {
    it('generates a draft and stores it with the base version', async () => {
        vi.mocked(scheduleDraftApi.generate).mockResolvedValue(draftResponse);
        const store = useScheduleDraftStore();
        const response = await store.generate('2026-08-17', '2026-08-23');
        expect(response?.base_version).toBe(5);
        expect(store.draft?.assignments).toHaveLength(1);
        expect(store.draft?.unassigned).toHaveLength(1);
        expect(store.draftBaseVersion).toBe(5);
    });

    it('applies a draft and records the result', async () => {
        vi.mocked(scheduleDraftApi.generate).mockResolvedValue(draftResponse);
        vi.mocked(scheduleDraftApi.applyDraft).mockResolvedValue({ version: 6, applied: true, assignments: [] });
        const store = useScheduleDraftStore();
        await store.generate('2026-08-17', '2026-08-23');
        const ok = await store.applyDraft();
        expect(ok).toBe(true);
        expect(store.draftApplyResult?.applied).toBe(true);
        expect(store.draftApplyResult?.version).toBe(6);
    });

    it('proposes a reschedule and stores the proposal', async () => {
        vi.mocked(scheduleDraftApi.propose).mockResolvedValue(proposalResponse);
        const store = useScheduleDraftStore();
        const response = await store.propose('2026-08-17', '2026-08-23');
        expect(response?.has_changes).toBe(true);
        expect(store.proposal?.moves).toHaveLength(1);
        expect(store.proposal?.conflict_task_ids).toEqual(['3']);
    });

    it('applies a proposal and records the result', async () => {
        vi.mocked(scheduleDraftApi.propose).mockResolvedValue(proposalResponse);
        vi.mocked(scheduleDraftApi.applyProposal).mockResolvedValue({ version: 6, applied: true, conflict_task_ids: ['3'] });
        const store = useScheduleDraftStore();
        await store.propose('2026-08-17', '2026-08-23');
        const ok = await store.applyProposal();
        expect(ok).toBe(true);
        expect(store.rescheduleApplyResult?.applied).toBe(true);
        expect(store.rescheduleApplyResult?.conflict_task_ids).toEqual(['3']);
    });
});
