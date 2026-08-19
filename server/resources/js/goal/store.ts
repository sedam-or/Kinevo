import { defineStore } from 'pinia';
import { ref } from 'vue';
import { goalApi } from './api';
import type { ApiError } from '../api/types';
import type { Goal, Milestone, Program } from './types';

export const useGoalStore = defineStore('goal', () => {
    const goals = ref<Goal[]>([]);
    const programs = ref<Program[]>([]);
    const currentGoal = ref<Goal | null>(null);
    const milestones = ref<Milestone[]>([]);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    async function loadAll(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const [g, p] = await Promise.all([goalApi.goals(), goalApi.programs()]);
            goals.value = g.goals;
            programs.value = p.programs;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function loadGoal(goalId: number): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const [goalRes, msRes] = await Promise.all([
                goalApi.goal(goalId),
                goalApi.milestones(goalId),
            ]);
            currentGoal.value = goalRes.goal;
            milestones.value = msRes.milestones;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function createGoal(payload: Parameters<typeof goalApi.createGoal>[0]): Promise<Goal | null> {
        error.value = null;
        try {
            const { goal } = await goalApi.createGoal(payload);
            goals.value = [goal, ...goals.value];
            return goal;
        } catch (err) {
            error.value = err as ApiError;
            return null;
        }
    }

    async function createProgram(payload: Parameters<typeof goalApi.createProgram>[0]): Promise<Program | null> {
        error.value = null;
        try {
            const { program } = await goalApi.createProgram(payload);
            programs.value = [program, ...programs.value];
            return program;
        } catch (err) {
            error.value = err as ApiError;
            return null;
        }
    }

    async function createMilestone(goalId: number, payload: Parameters<typeof goalApi.createMilestone>[1]): Promise<void> {
        error.value = null;
        try {
            const { milestone } = await goalApi.createMilestone(goalId, payload);
            milestones.value = [...milestones.value, milestone].sort((a, b) => a.sequence - b.sequence);
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function setGoalStatus(goalId: number, status: string): Promise<void> {
        error.value = null;
        try {
            const { goal } = await goalApi.setGoalStatus(goalId, status);
            replaceGoal(goal);
            if (currentGoal.value?.id === goalId) {
                currentGoal.value = goal;
            }
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function setMilestoneStatus(goalId: number, milestoneId: number, status: string): Promise<void> {
        error.value = null;
        try {
            const { milestone } = await goalApi.setMilestoneStatus(goalId, milestoneId, status);
            milestones.value = milestones.value.map((m) => (m.id === milestone.id ? milestone : m));
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function setProgramStatus(programId: number, status: string): Promise<void> {
        error.value = null;
        try {
            const { program } = await goalApi.setProgramStatus(programId, status);
            programs.value = programs.value.map((p) => (p.id === program.id ? program : p));
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    function replaceGoal(goal: Goal): void {
        goals.value = goals.value.map((g) => (g.id === goal.id ? goal : g));
    }

    function clearGoal(): void {
        currentGoal.value = null;
        milestones.value = [];
    }

    return {
        goals,
        programs,
        currentGoal,
        milestones,
        loading,
        error,
        loadAll,
        loadGoal,
        createGoal,
        createProgram,
        createMilestone,
        setGoalStatus,
        setMilestoneStatus,
        setProgramStatus,
        clearGoal,
    };
});
