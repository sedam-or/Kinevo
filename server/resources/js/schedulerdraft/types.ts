export interface DraftAssignment {
    task_id: string;
    title: string;
    start: string;
    end: string;
}

export interface UnassignedTask {
    task_id: string;
    title: string;
    reason: string;
}

export interface ScheduleDraft {
    assignments: DraftAssignment[];
    unassigned: UnassignedTask[];
}

export interface DraftResponse {
    draft: ScheduleDraft;
    base_version: number;
}

export interface DraftApplyResponse {
    version: number;
    applied: boolean;
    assignments: Record<string, unknown>[];
}

export interface TimeSlot {
    start: string;
    end: string;
}

export interface ProposalMove {
    task_id: string;
    title: string;
    from: TimeSlot | null;
    to: TimeSlot;
}

export interface RescheduleProposal {
    base_version: number;
    new_version: number;
    moves: ProposalMove[];
    conflict_task_ids: string[];
}

export interface RescheduleResponse {
    proposal: RescheduleProposal;
    has_changes: boolean;
}

export interface RescheduleApplyResponse {
    version: number;
    applied: boolean;
    conflict_task_ids: string[];
}

/** ADR-016 §2.2 — manual Sync Now response. */
export interface SyncNowResponse {
    status: 'no_changes' | 'proposal' | 'run_in_progress';
    needs_review: boolean;
    base_version: number;
    new_version: number | null;
    proposal: RescheduleProposal | null;
}

/** ADR-016 §2.5 — persisted (weekly) draft record. */
export interface ScheduleDraftRecord {
    id: number;
    source: string;
    status: 'pending' | 'applied' | 'discarded' | 'superseded';
    stale: boolean;
    payload: DraftResponse;
    base_version: number;
    horizon_from: string;
    horizon_to: string;
    generated_for_week: string | null;
    created_at: string | null;
}

export interface ScheduleDraftsResponse {
    drafts: ScheduleDraftRecord[];
    base_version: number;
}
