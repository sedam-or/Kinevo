import { apiClient } from '../api/client';
import type {
    CanvasOption,
    CreateKnowledgeLinkPayload,
    GoalOption,
    KnowledgeLinkListResponse,
    KnowledgeLinkResponse,
    KnowledgeTargetType,
    MilestoneOption,
    NoteOption,
    ProgramOption,
    TaskOption,
} from './types';

interface GoalListResponse {
    goals: GoalOption[];
}

interface ProgramListResponse {
    programs: ProgramOption[];
}

interface TaskListResponse {
    tasks: TaskOption[];
}

interface MilestoneListResponse {
    milestones: MilestoneOption[];
}

interface CanvasListResponse {
    canvases: CanvasOption[];
}

interface NoteListResponse {
    notes: NoteOption[];
}

export const knowledgeLinkApi = {
    linksForNote(noteId: number): Promise<KnowledgeLinkListResponse> {
        return apiClient.request<KnowledgeLinkListResponse>(`/notes/${noteId}/links`);
    },

    createForNote(noteId: number, payload: CreateKnowledgeLinkPayload): Promise<KnowledgeLinkResponse> {
        return apiClient.request<KnowledgeLinkResponse>(`/notes/${noteId}/links`, {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    removeFromNote(noteId: number, linkId: number): Promise<void> {
        return apiClient.request<void>(`/notes/${noteId}/links/${linkId}`, { method: 'DELETE' });
    },

    linksForCanvas(canvasId: number): Promise<KnowledgeLinkListResponse> {
        return apiClient.request<KnowledgeLinkListResponse>(`/canvases/${canvasId}/links`);
    },

    createForCanvas(canvasId: number, payload: CreateKnowledgeLinkPayload): Promise<KnowledgeLinkResponse> {
        return apiClient.request<KnowledgeLinkResponse>(`/canvases/${canvasId}/links`, {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    },

    removeFromCanvas(canvasId: number, linkId: number): Promise<void> {
        return apiClient.request<void>(`/canvases/${canvasId}/links/${linkId}`, { method: 'DELETE' });
    },

    reverseLinks(targetType: KnowledgeTargetType, targetId: number): Promise<KnowledgeLinkListResponse> {
        return apiClient.request<KnowledgeLinkListResponse>(
            `/knowledge/links?target_type=${encodeURIComponent(targetType)}&target_id=${targetId}`,
        );
    },

    goals(): Promise<GoalListResponse> {
        return apiClient.request<GoalListResponse>('/goals');
    },

    programs(): Promise<ProgramListResponse> {
        return apiClient.request<ProgramListResponse>('/programs');
    },

    tasks(): Promise<TaskListResponse> {
        return apiClient.request<TaskListResponse>('/tasks');
    },

    milestones(goalId: number): Promise<MilestoneListResponse> {
        return apiClient.request<MilestoneListResponse>(`/goals/${goalId}/milestones`);
    },

    canvases(): Promise<CanvasListResponse> {
        return apiClient.request<CanvasListResponse>('/canvases');
    },

    notes(): Promise<NoteListResponse> {
        return apiClient.request<NoteListResponse>('/notes');
    },
};
