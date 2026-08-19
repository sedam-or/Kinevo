import { apiClient } from '../api/client';
import type {
    CreateGoalPayload,
    CreateMilestonePayload,
    CreateProgramPayload,
    GoalListResponse,
    GoalResponse,
    MilestoneListResponse,
    MilestoneResponse,
    ProgramListResponse,
    ProgramResponse,
} from './types';

export const goalApi = {
    goals(): Promise<GoalListResponse> {
        return apiClient.request<GoalListResponse>('/goals');
    },

    goal(goalId: number): Promise<GoalResponse> {
        return apiClient.request<GoalResponse>(`/goals/${goalId}`);
    },

    createGoal(payload: CreateGoalPayload): Promise<GoalResponse> {
        return apiClient.request<GoalResponse>('/goals', { method: 'POST', body: JSON.stringify(payload) });
    },

    setGoalStatus(goalId: number, status: string): Promise<GoalResponse> {
        return apiClient.request<GoalResponse>(`/goals/${goalId}/status`, {
            method: 'POST',
            body: JSON.stringify({ status }),
        });
    },

    milestones(goalId: number): Promise<MilestoneListResponse> {
        return apiClient.request<MilestoneListResponse>(`/goals/${goalId}/milestones`);
    },

    createMilestone(goalId: number, payload: CreateMilestonePayload): Promise<MilestoneResponse> {
        return apiClient.request<MilestoneResponse>(`/goals/${goalId}/milestones`, {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    setMilestoneStatus(goalId: number, milestoneId: number, status: string): Promise<MilestoneResponse> {
        return apiClient.request<MilestoneResponse>(`/goals/${goalId}/milestones/${milestoneId}/status`, {
            method: 'POST',
            body: JSON.stringify({ status }),
        });
    },

    programs(): Promise<ProgramListResponse> {
        return apiClient.request<ProgramListResponse>('/programs');
    },

    createProgram(payload: CreateProgramPayload): Promise<ProgramResponse> {
        return apiClient.request<ProgramResponse>('/programs', { method: 'POST', body: JSON.stringify(payload) });
    },

    setProgramStatus(programId: number, status: string): Promise<ProgramResponse> {
        return apiClient.request<ProgramResponse>(`/programs/${programId}/status`, {
            method: 'POST',
            body: JSON.stringify({ status }),
        });
    },
};
