export type WorkLifeBand = 'no_data' | 'work_leaning' | 'balanced' | 'recharge_leaning';

export interface WorkLifeDay {
    date: string;
    productive_minutes: number;
    recharge_minutes: number;
    work_ratio: number;
    recharge_ratio: number;
    band: WorkLifeBand;
}

export interface WorkLifeAnalyticsResponse {
    from: string;
    to: string;
    productive_minutes: number;
    recharge_minutes: number;
    total_minutes: number;
    work_ratio: number;
    recharge_ratio: number;
    band: WorkLifeBand;
    days: WorkLifeDay[];
    disclaimer: string;
}

export type DeadlineHealth = 'completed' | 'on_track' | 'at_risk' | 'overdue' | 'no_deadline';

export interface GoalSummary {
    id: number;
    title: string;
    status: string;
    progress: number;
    milestones_total: number;
    milestones_completed: number;
    tasks_total: number;
    tasks_completed: number;
    days_remaining: number | null;
    deadline_health: DeadlineHealth;
}

export interface ProgramContribution {
    id: number;
    name: string;
    status: string;
    tasks_total: number;
    tasks_completed: number;
    workload_completion: number;
}

export interface GoalProgressAnalyticsResponse {
    total_goals: number;
    completed_goals: number;
    completion_rate: number;
    total_milestones: number;
    completed_milestones: number;
    goals: GoalSummary[];
    programs: ProgramContribution[];
    deadline_health: Record<DeadlineHealth, number>;
    goal_tasks_total: number;
    goal_tasks_completed: number;
    workload_completion: number;
}

export interface TaskCompletionAnalyticsResponse {
    from: string;
    to: string;
    total_tasks: number;
    completed_tasks: number;
    completion_rate: number;
    completed_in_period: number;
    by_status: Record<string, number>;
}

export interface CapacityDay {
    date: string;
    scheduled_minutes: number;
    available_minutes: number;
    overload_minutes: number;
    status: 'ok' | 'overload';
}

export interface CapacityAnalyticsResponse {
    from: string;
    to: string;
    weeks: { week_start: string; planned_minutes: number; completed_minutes: number; realization: number; tag: string }[];
    days: CapacityDay[];
    realization_ratio: number;
    average_realization: number;
    confidence: 'HIGH' | 'MEDIUM' | 'LOW';
    recommendation: 'MAINTAIN' | 'REDUCE_LOAD' | 'BOOST_AVAILABLE';
    reason: string;
    target_capacity_minutes: number;
}

export interface ActivityAnalyticsResponse {
    from: string;
    to: string;
    total_events: number;
    by_type: Record<string, number>;
    recent: { id: number | null; event_type: string; entity_type: string; entity_id: number; title: string | null; event_at: string }[];
}

export interface FocusAnalyticsResponse {
    from: string;
    to: string;
    total_sessions: number;
    total_minutes: number;
    days: { date: string; sessions: number; minutes: number }[];
}

export interface ProgressEventsAnalyticsResponse {
    from: string;
    to: string;
    total_events: number;
    by_type: Record<string, number>;
    recent: { id: number | null; event_type: string; entity_type: string; entity_id: number; title: string | null; occurred_at: string }[];
}

export type PillarKey = 'karier' | 'kesehatan' | 'bahasa' | 'branding' | 'uncategorized';

export interface PillarRow {
    key: PillarKey;
    label: string;
    realization_minutes: number;
    target_minutes: number;
    percent: number | null;
}

export interface PillarAnalyticsResponse {
    from: string;
    to: string;
    pillars: PillarRow[];
}

export interface HeatmapDay {
    date: string;
    productive_minutes: number;
    recharge_minutes: number;
    completion_count: number;
    progress_events: number;
    intensity: number;
}

export interface HeatmapLegendItem {
    level: number;
    label: string;
    description: string;
}

export interface HeatmapAnalyticsResponse {
    from: string;
    to: string;
    pillar: PillarKey | null;
    days: HeatmapDay[];
    legend: HeatmapLegendItem[];
}

export interface AnalyticsOverviewResponse {
    from: string;
    to: string;
    work_life: WorkLifeAnalyticsResponse;
    task_completion: TaskCompletionAnalyticsResponse;
    goal_progress: GoalProgressAnalyticsResponse;
    capacity: CapacityAnalyticsResponse;
    activity: ActivityAnalyticsResponse;
    focus: FocusAnalyticsResponse;
    progress_events: ProgressEventsAnalyticsResponse;
    pillars: PillarAnalyticsResponse;
}