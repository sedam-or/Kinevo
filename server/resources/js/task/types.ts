export interface Task {
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
    workspace_id?: number | null;
    /** ADR-016 §2.10 — Sacred Anchor producer flag (at most one per user). */
    is_sacred_anchor?: boolean;
    created_at?: string;
    updated_at?: string;
}

export type TaskStatusValue =
    | 'backlog'
    | 'scheduled'
    | 'in_progress'
    | 'partial'
    | 'continued'
    | 'completed'
    | 'skipped'
    | 'missed'
    | 'conflict';

export interface Subtask {
    id: number;
    user_id: number;
    task_id: number;
    title: string;
    notes: string | null;
    sequence: number;
    completed: boolean;
    version: number;
    created_at?: string;
    updated_at?: string;
}

export interface TaskListResponse {
    tasks: Task[];
}

export interface TaskResponse {
    task: Task;
}

export interface TaskDetailResponse extends TaskResponse {
    /** ADR-015: active placement lock state (null when the task has no placement). */
    assignment_locked?: boolean | null;
}

export interface AssignmentLockResponse {
    assignment: {
        id: number;
        task_id: number;
        locked: boolean;
        start_at: string;
        end_at: string;
        version: number;
    };
}

export interface SubtaskListResponse {
    subtasks: Subtask[];
}

export interface SubtaskResponse {
    subtask: Subtask;
}

export interface ToggleSubtaskResponse {
    subtask: Subtask;
    task: Task;
}

export interface PartialCompleteResponse {
    task: Task;
    continuation: Task | null;
}

export interface PromoteSubtaskResponse {
    task: Task;
    source_task: Task;
}

export interface CreateTaskPayload {
    title: string;
    description?: string | null;
    program_id?: number | null;
    goal_id?: number | null;
    milestone_id?: number | null;
    priority_tier?: number;
    estimated_minutes?: number | null;
    due_at?: string | null;
}

export interface UpdateTaskPayload {
    title?: string;
    description?: string | null;
    program_id?: number | null;
    goal_id?: number | null;
    milestone_id?: number | null;
    priority_tier?: number;
    estimated_minutes?: number | null;
    due_at?: string | null;
}

export const TASK_STATUSES: TaskStatusValue[] = [
    'backlog',
    'scheduled',
    'in_progress',
    'partial',
    'continued',
    'completed',
    'skipped',
    'missed',
    'conflict',
];

/**
 * Allowed status transitions (TaskStatus VO TRANSITIONS). Frontend uses these
 * only to present valid actions; the backend remains the state authority.
 */
export const TASK_TRANSITIONS: Record<TaskStatusValue, TaskStatusValue[]> = {
    backlog: ['scheduled', 'in_progress', 'completed', 'skipped'],
    scheduled: ['in_progress', 'missed', 'conflict', 'skipped', 'backlog'],
    in_progress: ['completed', 'partial', 'conflict', 'skipped'],
    partial: ['continued', 'completed', 'scheduled', 'skipped'],
    continued: ['scheduled', 'in_progress', 'completed', 'skipped'],
    missed: ['backlog', 'scheduled', 'completed'],
    conflict: ['scheduled', 'in_progress', 'backlog'],
    completed: [],
    skipped: [],
};
