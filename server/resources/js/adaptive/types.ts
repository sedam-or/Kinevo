/** Lightweight adaptive-context check-in types (design.md §23, SRS FR-40). */

export interface AdaptiveObservation {
    id: number;
    user_id: number;
    task_id: number | null;
    energy_level: number | null;
    stress_level: number | null;
    task_difficulty: number | null;
    skill_familiarity: number | null;
    interruption_count: number | null;
    focus_duration_minutes: number | null;
    checked_at: string | null;
    created_at?: string;
}

export interface AdaptiveObservationListResponse {
    observations: AdaptiveObservation[];
}

export interface AdaptiveObservationResponse {
    observation: AdaptiveObservation;
}

export interface AdaptiveCheckInPayload {
    task_id?: number | null;
    energy_level?: number | null;
    stress_level?: number | null;
    task_difficulty?: number | null;
    skill_familiarity?: number | null;
    checked_at?: string | null;
}

export interface BurnoutSignalResponse {
    signal: {
        level: 'none' | 'early' | 'elevated' | 'high';
        score: number;
        reason: string;
    };
}