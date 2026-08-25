import { defineStore } from 'pinia';
import { ref } from 'vue';
import { useWorkspaceStore } from '../workspace/store';
import { apiClient } from '../api/client';
import type { ApiError } from '../api/types';
import type { Goal, Milestone, Program } from '../goal/types';

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

export interface QuickCaptureTask {
    id: number;
    title: string;
    status: string;
    [key: string]: unknown;
}

export interface QuickCaptureAssignment {
    id: number;
    task_id: number;
    date: string;
    start_at: string;
    end_at: string;
    [key: string]: unknown;
}

export interface QuickCaptureResult {
    task: QuickCaptureTask;
    placed: boolean;
    assignment: QuickCaptureAssignment | null;
    code: 'PLACED' | 'TASK_NO_CAPACITY';
    strategies: ('manual_swap' | 'auto_swap' | 'schedule_later')[];
}

export interface AutoSwapResult {
    task: QuickCaptureTask;
    applied: boolean;
    assignment: QuickCaptureAssignment | null;
    swapped_task: unknown | null;
    explanation: string;
}

export const useQuickCaptureStore = defineStore('quickCapture', () => {
    const open = ref(false);
    const busy = ref(false);
    const error = ref<ApiError | null>(null);
    const lastResult = ref<QuickCaptureResult | null>(null);
    const autoSwapResult = ref<AutoSwapResult | null>(null);

    const goals = ref<Goal[]>([]);
    const programs = ref<Program[]>([]);
    const milestones = ref<Milestone[]>([]);

    function show(): void {
        open.value = true;
        lastResult.value = null;
        autoSwapResult.value = null;
        error.value = null;
    }

    function hide(): void {
        open.value = false;
        lastResult.value = null;
        autoSwapResult.value = null;
    }

    async function loadContext(): Promise<void> {
        try {
            const [g, p] = await Promise.all([
                apiClient.request<{ goals: Goal[] }>('/goals'),
                apiClient.request<{ programs: Program[] }>('/programs'),
            ]);
            goals.value = g.goals;
            programs.value = p.programs;
        } catch {
            // dropdown context is best-effort
        }
    }

    async function loadMilestones(goalId: number): Promise<void> {
        try {
            const res = await apiClient.request<{ milestones: Milestone[] }>(`/goals/${goalId}/milestones`);
            milestones.value = res.milestones;
        } catch {
            milestones.value = [];
        }
    }

    function clearMilestones(): void {
        milestones.value = [];
    }

    async function submit(payload: QuickCapturePayload): Promise<QuickCaptureResult | null> {
        // TASK-P19-024 — default context = declared active workspace.
        const wid = useWorkspaceStore().activeWorkspaceId;
        if (wid !== null) {
            payload = { ...payload, workspace_id: wid };
        }
        busy.value = true;
        error.value = null;
        lastResult.value = null;
        autoSwapResult.value = null;
        try {
            const result = await apiClient.request<QuickCaptureResult>('/quick-capture', {
                method: 'POST',
                body: JSON.stringify(payload),
                noRetry: true,
            });
            lastResult.value = result;
            return result;
        } catch (err) {
            error.value = err as ApiError;
            return null;
        } finally {
            busy.value = false;
        }
    }

    async function autoSwap(taskId: number, date: string, durationMinutes: number): Promise<AutoSwapResult | null> {
        busy.value = true;
        error.value = null;
        try {
            const result = await apiClient.request<AutoSwapResult>(`/tasks/${taskId}/auto-swap`, {
                method: 'POST',
                body: JSON.stringify({ date, duration_minutes: durationMinutes }),
                noRetry: true,
            });
            autoSwapResult.value = result;
            return result;
        } catch (err) {
            error.value = err as ApiError;
            return null;
        } finally {
            busy.value = false;
        }
    }

    return {
        open,
        busy,
        error,
        lastResult,
        autoSwapResult,
        goals,
        programs,
        milestones,
        show,
        hide,
        loadContext,
        loadMilestones,
        clearMilestones,
        submit,
        autoSwap,
    };
});
