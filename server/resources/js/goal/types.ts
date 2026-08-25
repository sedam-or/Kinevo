export interface Goal {
    id: number;
    user_id: number;
    title: string;
    description: string | null;
    horizon: string;
    start_date: string | null;
    target_date: string | null;
    target_metric: string | null;
    status: string;
    priority_tier: number;
    progress_mode: string;
    progress: number;
    created_at?: string;
    updated_at?: string;
}

export interface Milestone {
    id: number;
    user_id: number;
    goal_id: number;
    title: string;
    description: string | null;
    sequence: number;
    target_date: string | null;
    estimated_minutes: number | null;
    status: string;
    progress_mode: string;
    progress: number;
    completed_at: string | null;
    version: number;
    created_at?: string;
    updated_at?: string;
}

export interface Program {
    id: number;
    user_id: number;
    name: string;
    description: string | null;
    category: string | null;
    workload_type: string;
    weekly_target_minutes: number | null;
    min_weekly_minutes: number | null;
    max_weekly_minutes: number | null;
    status: string;
    priority_tier: number;
    version: number;
    created_at?: string;
    updated_at?: string;
}

export interface GoalListResponse {
    goals: Goal[];
}

export interface GoalResponse {
    goal: Goal;
}

export interface MilestoneListResponse {
    milestones: Milestone[];
}

export interface MilestoneResponse {
    milestone: Milestone;
}

export interface ProgramListResponse {
    programs: Program[];
}

export interface ProgramResponse {
    program: Program;
}

export interface CreateGoalPayload {
    title: string;
    description?: string | null;
    horizon: string;
    start_date?: string | null;
    target_date?: string | null;
    target_metric?: string | null;
    priority_tier?: number;
    workspace_id?: number;
}

export interface CreateMilestonePayload {
    title: string;
    description?: string | null;
    sequence?: number;
    target_date?: string | null;
    estimated_minutes?: number | null;
}

export interface CreateProgramPayload {
    name: string;
    description?: string | null;
    category?: string | null;
    workload_type: string;
    weekly_target_minutes?: number | null;
    min_weekly_minutes?: number | null;
    max_weekly_minutes?: number | null;
    priority_tier?: number;
}

export const GOAL_HORIZONS = ['yearly', 'quarterly', 'monthly', 'custom'] as const;

export const GOAL_STATUSES = ['draft', 'active', 'paused', 'completed', 'archived', 'dropped'] as const;

export const MILESTONE_STATUSES = ['planned', 'active', 'blocked', 'completed', 'dropped'] as const;

export const PROGRAM_WORKLOAD_TYPES = ['structured', 'range', 'flexible'] as const;

export const PROGRAM_STATUSES = ['active', 'paused', 'completed', 'dropped'] as const;
