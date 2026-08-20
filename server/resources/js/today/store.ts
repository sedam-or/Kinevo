import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { todayApi } from './api';
import type { BreakPeriodInfo, CapacityIndicators, EmptySlot, PauseInfo, TodayEvent, TodayResponse } from './types';
import type { ApiError } from '../api/types';

export const useTodayStore = defineStore('today', () => {
    const date = ref<string | null>(null);
    const scheduleVersion = ref<number | null>(null);
    const pause = ref<PauseInfo | null>(null);
    const breakPeriod = ref<BreakPeriodInfo | null>(null);
    const events = ref<TodayEvent[]>([]);
    const emptySlots = ref<EmptySlot[]>([]);
    const hardLandscape = ref<TodayResponse['hard_landscape']>([]);
    const capacity = ref<CapacityIndicators | null>(null);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    const hasData = computed(() => events.value.length > 0 || emptySlots.value.length > 0);

    function apply(response: TodayResponse): void {
        date.value = response.date;
        scheduleVersion.value = response.schedule_version;
        pause.value = response.pause ?? null;
        breakPeriod.value = response.break ?? null;
        events.value = response.events;
        emptySlots.value = response.empty_slots;
        hardLandscape.value = response.hard_landscape;
        capacity.value = response.capacity;
        error.value = null;
    }

    async function load(targetDate: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            apply(await todayApi.today(targetDate));
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    function clear(): void {
        date.value = null;
        scheduleVersion.value = null;
        pause.value = null;
        breakPeriod.value = null;
        events.value = [];
        emptySlots.value = [];
        hardLandscape.value = [];
        capacity.value = null;
        error.value = null;
    }

    return {
        date,
        scheduleVersion,
        pause,
        breakPeriod,
        events,
        emptySlots,
        hardLandscape,
        capacity,
        loading,
        error,
        hasData,
        load,
        clear,
    };
});
