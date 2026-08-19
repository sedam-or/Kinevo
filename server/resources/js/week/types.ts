export interface WeekDay {
    date: string;
    weekday: number;
    task_count: number;
    scheduled_minutes: number;
}

export interface WeekResponse {
    start: string;
    end: string;
    schedule_version: number;
    days: WeekDay[];
}

export interface CalendarDay {
    date: string;
    day: number;
    task_count: number;
    scheduled_minutes: number;
}

export interface CalendarResponse {
    year: number;
    month: number;
    schedule_version: number;
    days: CalendarDay[];
}

export interface Assignment {
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

export interface ScheduleTask {
    id: number;
    title: string;
    status: string;
    priority_tier: number;
    due_at: string | null;
    estimated_minutes: number | null;
    progress: number;
    [key: string]: unknown;
}

export interface ScheduleRangeEvent {
    assignment: Assignment;
    locked: boolean;
    conflict: boolean;
    task: ScheduleTask | null;
    program: { name?: string } | null;
    goal: { title?: string } | null;
    milestone: { title?: string } | null;
}

export interface ScheduleRangeResponse {
    from: string;
    to: string;
    schedule_version: number;
    events: ScheduleRangeEvent[];
}
