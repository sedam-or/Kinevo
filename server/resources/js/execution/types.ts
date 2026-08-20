export type ExecutionStatus = 'running' | 'paused' | 'completed' | 'abandoned';

export interface ExecutionSession {
    id: number;
    user_id: number;
    task_id: number;
    status: ExecutionStatus;
    started_at: string;
    last_resumed_at: string | null;
    accumulated_seconds: number;
    elapsed_seconds: number;
    ended_at: string | null;
}

export interface ExecutionActiveResponse {
    execution: ExecutionSession | null;
}

export interface ExecutionListResponse {
    executions: ExecutionSession[];
}

export interface ExecutionStartResponse {
    execution: ExecutionSession;
}

export interface ExecutionPauseResponse {
    execution: ExecutionSession;
}

export interface ExecutionResumeResponse {
    execution: ExecutionSession;
}

export interface ExecutionAbandonResponse {
    execution: ExecutionSession;
}

export interface ExecutionCompleteResponse {
    execution: ExecutionSession;
    focus_session: {
        id: number;
        user_id: number;
        task_id: number | null;
        started_at: string;
        ended_at: string;
        duration_minutes: number;
    };
    task: {
        id: number;
        status: string;
        progress: number;
        version: number;
        title: string;
        [key: string]: unknown;
    };
    continuation: {
        id: number;
        status: string;
        [key: string]: unknown;
    } | null;
}