export interface TodayTask {
    id: number;
    user_id: number;
    program_id: number | null;
    goal_id: number | null;
    milestone_id: number | null;
    title: string;
    description: string | null;
    status: string;
    priority_tier: number;
    estimated_minutes: number | null;
    due_at: string | null;
    progress: number;
    version: number;
    created_at?: string;
    updated_at?: string;
}

export interface ScheduleAssignment {
    id: number;
    user_id: number;
    task_id: number;
    date: string;
    start_at: string;
    end_at: string;
    duration_minutes: number;
    status: string;
    source: string;
    schedule_version: number;
    locked: boolean;
    version: number;
    created_at?: string;
    updated_at?: string;
}

export interface ContextEntity {
    id: number;
    title?: string;
    name?: string;
    [key: string]: unknown;
}

export interface TodayEvent {
    assignment: ScheduleAssignment;
    locked: boolean;
    conflict: boolean;
    task: TodayTask | null;
    program: ContextEntity | null;
    goal: ContextEntity | null;
    milestone: ContextEntity | null;
}

export interface EmptySlot {
    start: string;
    end: string;
    duration_minutes: number;
}

export interface HardLandscapeEvent {
    id: number;
    user_id: number;
    title?: string;
    start_at?: string;
    end_at?: string;
    start?: string;
    end?: string;
    type?: string;
    [key: string]: unknown;
}

export interface CapacityIndicators {
    scheduled_minutes: number;
    available_minutes: number;
    overload_minutes: number;
    status: 'ok' | 'overload';
}

export interface PauseInfo {
    type: 'emergency' | 'mini';
    week_start: string;
    week_end: string;
    keep_task_ids: string[];
    moved_task_ids: string[];
    conflict_task_ids: string[];
    schedule_version: number;
}

export interface BreakPeriodInfo {
    id: number;
    user_id: number;
    start_date: string;
    end_date: string;
    status: 'active' | 'ended';
    duration_days: number;
    created_at?: string | null;
}

export interface TodayResponse {
    date: string;
    schedule_version: number;
    /** ADR-016 §2.3 — reality changed and the accepted schedule may be impacted. */
    schedule_needs_review?: boolean;
    pause: PauseInfo | null;
    break: BreakPeriodInfo | null;
    events: TodayEvent[];
    empty_slots: EmptySlot[];
    hard_landscape: HardLandscapeEvent[];
    capacity: CapacityIndicators;
}

export interface QuickCapturePayload {
    title: string;
    priority_tier?: number;
    size?: 'cepat' | 'sedang' | 'berat';
    duration_minutes?: number | null;
    program_id?: number | null;
    goal_id?: number | null;
    date?: string | null;
    workspace_id?: number;
}

export interface QuickCaptureResponse {
    task: TodayTask;
    placed: boolean;
    assignment: ScheduleAssignment | null;
    code: 'PLACED' | 'TASK_NO_CAPACITY';
    strategies: ('manual_swap' | 'auto_swap' | 'schedule_later')[];
}

export interface MiniPausePayload {
    date: string;
}

export interface MiniPauseMove {
    task_id: string;
    title: string;
    from: { start: string; end: string } | null;
    to: { start: string; end: string };
}

export interface MiniPauseResponse {
    version: number;
    applied: boolean;
    moves: MiniPauseMove[];
    conflict_task_ids: string[];
    explanation: string;
}

export interface EmergencyPausePayload {
    date: string;
    keep_task_ids: number[];
}

export interface EmergencyPauseMove {
    task_id: string;
    title: string;
    from: { start: string; end: string } | null;
    to: { start: string; end: string };
}

export interface EmergencyPauseResponse {
    version: number;
    applied: boolean;
    week_start: string;
    week_end: string;
    keep_task_ids: string[];
    moves: EmergencyPauseMove[];
    conflict_task_ids: string[];
    explanation: string;
}

export interface StartBreakPayload {
    start_date: string;
    end_date: string;
}

export interface StartBreakResponse {
    break_period_id: number;
    start_date: string;
    end_date: string;
    explanation: string;
}

export interface EndBreakResponse {
    applied: boolean;
    break_period_id: number | null;
    start_date: string | null;
    end_date: string | null;
    duration_days: number | null;
    explanation: string;
}

export interface BoostTarget {
    id: number;
    user_id: number;
    break_period_id: number | null;
    start_date: string;
    end_date: string;
    target_percent: number;
    status: 'active' | 'ended';
    created_at?: string | null;
}

export interface BoostRecommendation {
    eligible: boolean;
    recommendation: 'NOT_ELIGIBLE' | 'SUPPRESSED' | 'BOOST_AVAILABLE' | 'MAINTAIN' | 'REDUCE_LOAD';
    reason: string;
    average_realization: number;
    recommended_target_percent: number;
}

export interface BoostSetupResponse {
    eligible: boolean;
    active_target: BoostTarget | null;
    recommendation: BoostRecommendation;
    safety_cap_percent: number;
    break_period_id: number | null;
    break_start_date: string | null;
    break_end_date: string | null;
}

export interface SetBoostTargetPayload {
    target_percent: number;
    break_period_id?: number;
    start_date?: string;
    end_date?: string;
}

export interface SetBoostTargetResponse {
    target: BoostTarget;
    capped: boolean;
    warning: string | null;
    explanation: string;
}

export interface EndBoostTargetResponse {
    applied: boolean;
    target_id: number | null;
    target_percent: number | null;
    start_date: string | null;
    end_date: string | null;
    explanation: string;
}
