export type RechargeStatus = 'running' | 'paused' | 'completed' | 'abandoned';

export interface RechargeSession {
    id: number;
    user_id: number;
    status: RechargeStatus;
    started_at: string;
    last_resumed_at: string | null;
    accumulated_seconds: number;
    elapsed_seconds: number;
    duration_minutes: number | null;
    ended_at: string | null;
}

export interface RechargeStatusResponse {
    recharge: RechargeSession | null;
    cue_available: boolean;
    completed_focus_today: number;
    due_recharges: number;
    completed_recharges_today: number;
    recharge_minutes_today: number;
    productive_minutes_today: number;
    work_ratio: number;
    recharge_ratio: number;
}

export interface RechargeListResponse {
    recharges: RechargeSession[];
}

export interface RechargeSessionResponse {
    recharge: RechargeSession;
}