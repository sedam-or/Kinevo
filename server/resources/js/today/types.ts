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

export interface TodayResponse {
    date: string;
    schedule_version: number;
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
}

export interface QuickCaptureResponse {
    task: TodayTask;
    placed: boolean;
    assignment: ScheduleAssignment | null;
    code: 'PLACED' | 'TASK_NO_CAPACITY';
    strategies: ('manual_swap' | 'auto_swap' | 'schedule_later')[];
}
