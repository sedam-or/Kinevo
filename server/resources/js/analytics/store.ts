import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { analyticsApi } from './api';
import type { AnalyticsOverviewResponse, CapacityDay, DeadlineHealth, GoalSummary, HeatmapDay, HeatmapLegendItem, PillarKey, PillarRow, ProgramContribution, WorkLifeBand, WorkLifeDay } from './types';
import type { ApiError } from '../api/types';

export const useAnalyticsStore = defineStore('analytics', () => {
    const days = ref<WorkLifeDay[]>([]);
    const productiveMinutes = ref(0);
    const rechargeMinutes = ref(0);
    const totalMinutes = ref(0);
    const workRatio = ref(0);
    const rechargeRatio = ref(0);
    const band = ref<WorkLifeBand>('no_data');
    const disclaimer = ref('');
    const from = ref('');
    const to = ref('');

    const goals = ref<GoalSummary[]>([]);
    const goalCompletionRate = ref(0);
    const goalTotalMilestones = ref(0);
    const goalCompletedMilestones = ref(0);
    const goalWorkloadCompletion = ref(0);
    const goalDeadlineHealth = ref<Record<DeadlineHealth, number>>({
        completed: 0,
        on_track: 0,
        at_risk: 0,
        overdue: 0,
        no_deadline: 0,
    });
    const programs = ref<ProgramContribution[]>([]);

    const capacityDays = ref<CapacityDay[]>([]);
    const capacityWeeks = ref<{ week_start: string; planned_minutes: number; completed_minutes: number; realization: number; tag: string }[]>([]);
    const capacityRealization = ref(0);
    const capacityRecommendation = ref('');
    const capacityReason = ref('');
    const capacityConfidence = ref('');

    const pillars = ref<PillarRow[]>([]);

    const heatmapDays = ref<HeatmapDay[]>([]);
    const heatmapLegend = ref<HeatmapLegendItem[]>([]);
    const heatmapPillar = ref<PillarKey | null>(null);
    const heatmapLoading = ref(false);
    const heatmapError = ref<ApiError | null>(null);

    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    const hasData = computed(() => totalMinutes.value > 0);
    const hasGoals = computed(() => goals.value.length > 0);

    function apply(result: AnalyticsOverviewResponse): void {
        const workLife = result.work_life;
        days.value = workLife.days;
        productiveMinutes.value = workLife.productive_minutes;
        rechargeMinutes.value = workLife.recharge_minutes;
        totalMinutes.value = workLife.total_minutes;
        workRatio.value = workLife.work_ratio;
        rechargeRatio.value = workLife.recharge_ratio;
        band.value = workLife.band;
        disclaimer.value = workLife.disclaimer;

        goals.value = result.goal_progress.goals;
        goalCompletionRate.value = result.goal_progress.completion_rate;
        goalTotalMilestones.value = result.goal_progress.total_milestones;
        goalCompletedMilestones.value = result.goal_progress.completed_milestones;
        goalWorkloadCompletion.value = result.goal_progress.workload_completion;
        goalDeadlineHealth.value = result.goal_progress.deadline_health;
        programs.value = result.goal_progress.programs;

        capacityDays.value = result.capacity.days;
        capacityWeeks.value = result.capacity.weeks;
        capacityRealization.value = result.capacity.realization_ratio;
        capacityRecommendation.value = result.capacity.recommendation;
        capacityReason.value = result.capacity.reason;
        capacityConfidence.value = result.capacity.confidence;

        pillars.value = result.pillars.pillars;

        from.value = result.from;
        to.value = result.to;
    }

    async function load(fromDate: string, toDate: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const result = await analyticsApi.overview(fromDate, toDate);
            apply(result);
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function loadHeatmap(fromDate: string, toDate: string, pillar?: PillarKey): Promise<void> {
        heatmapLoading.value = true;
        heatmapError.value = null;
        try {
            const result = await analyticsApi.heatmap(fromDate, toDate, pillar ?? undefined);
            heatmapDays.value = result.days;
            heatmapLegend.value = result.legend;
            heatmapPillar.value = result.pillar;
        } catch (err) {
            heatmapError.value = err as ApiError;
        } finally {
            heatmapLoading.value = false;
        }
    }

    function clear(): void {
        days.value = [];
        productiveMinutes.value = 0;
        rechargeMinutes.value = 0;
        totalMinutes.value = 0;
        workRatio.value = 0;
        rechargeRatio.value = 0;
        band.value = 'no_data';
        disclaimer.value = '';
        goals.value = [];
        goalCompletionRate.value = 0;
        goalTotalMilestones.value = 0;
        goalCompletedMilestones.value = 0;
        goalWorkloadCompletion.value = 0;
        goalDeadlineHealth.value = { completed: 0, on_track: 0, at_risk: 0, overdue: 0, no_deadline: 0 };
        programs.value = [];
        capacityDays.value = [];
        capacityWeeks.value = [];
        capacityRealization.value = 0;
        capacityRecommendation.value = '';
        capacityReason.value = '';
        capacityConfidence.value = '';
        pillars.value = [];
        heatmapDays.value = [];
        heatmapLegend.value = [];
        heatmapPillar.value = null;
        heatmapError.value = null;
        from.value = '';
        to.value = '';
        error.value = null;
    }

    return {
        days,
        productiveMinutes,
        rechargeMinutes,
        totalMinutes,
        workRatio,
        rechargeRatio,
        band,
        disclaimer,
        from,
        to,
        goals,
        goalCompletionRate,
        goalTotalMilestones,
        goalCompletedMilestones,
        goalWorkloadCompletion,
        goalDeadlineHealth,
        programs,
        capacityDays,
        capacityWeeks,
        capacityRealization,
        capacityRecommendation,
        capacityReason,
        capacityConfidence,
        pillars,
        heatmapDays,
        heatmapLegend,
        heatmapPillar,
        heatmapLoading,
        heatmapError,
        loading,
        error,
        hasData,
        hasGoals,
        load,
        loadHeatmap,
        clear,
    };
});