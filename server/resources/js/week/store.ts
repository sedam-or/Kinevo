import { defineStore } from 'pinia';
import { ref } from 'vue';
import { weekApi } from './api';
import type { ApiError } from '../api/types';
import type { CalendarDay, ScheduleRangeEvent, WeekDay } from './types';

export const useWeekStore = defineStore('week', () => {
    const weekDays = ref<WeekDay[]>([]);
    const weekStart = ref<string | null>(null);
    const weekEnd = ref<string | null>(null);
    const calendarDays = ref<CalendarDay[]>([]);
    const calendarYear = ref<number | null>(null);
    const calendarMonth = ref<number | null>(null);
    const rangeEvents = ref<ScheduleRangeEvent[]>([]);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    async function loadWeek(date: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const response = await weekApi.week(date);
            weekStart.value = response.start;
            weekEnd.value = response.end;
            weekDays.value = response.days;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function loadCalendar(month: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const response = await weekApi.calendar(month);
            calendarYear.value = response.year;
            calendarMonth.value = response.month;
            calendarDays.value = response.days;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function loadRange(from: string, to: string): Promise<void> {
        try {
            const response = await weekApi.range(from, to);
            rangeEvents.value = response.events;
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    return {
        weekDays,
        weekStart,
        weekEnd,
        calendarDays,
        calendarYear,
        calendarMonth,
        rangeEvents,
        loading,
        error,
        loadWeek,
        loadCalendar,
        loadRange,
    };
});
