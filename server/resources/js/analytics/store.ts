import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { analyticsApi } from './api';
import type { WorkLifeAnalyticsResponse, WorkLifeDay } from './types';
import type { ApiError } from '../api/types';

export const useAnalyticsStore = defineStore('analytics', () => {
    const days = ref<WorkLifeDay[]>([]);
    const productiveMinutes = ref(0);
    const rechargeMinutes = ref(0);
    const totalMinutes = ref(0);
    const workRatio = ref(0);
    const rechargeRatio = ref(0);
    const band = ref<WorkLifeAnalyticsResponse['band']>('no_data');
    const disclaimer = ref('');
    const from = ref('');
    const to = ref('');
    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    const hasData = computed(() => totalMinutes.value > 0);

    function apply(result: WorkLifeAnalyticsResponse): void {
        days.value = result.days;
        productiveMinutes.value = result.productive_minutes;
        rechargeMinutes.value = result.recharge_minutes;
        totalMinutes.value = result.total_minutes;
        workRatio.value = result.work_ratio;
        rechargeRatio.value = result.recharge_ratio;
        band.value = result.band;
        disclaimer.value = result.disclaimer;
        from.value = result.from;
        to.value = result.to;
    }

    async function load(fromDate: string, toDate: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const result = await analyticsApi.workLife(fromDate, toDate);
            apply(result);
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
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
        loading,
        error,
        hasData,
        load,
        clear,
    };
});