<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useWeekStore } from './store';
import type { ScheduleRangeEvent } from './types';

const props = defineProps<{
    anchorDate: string;
}>();

const week = useWeekStore();

const cursor = ref(props.anchorDate);

const weekdays = computed(() => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);

function iso(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function currentMonday(date: string): Date {
    const d = new Date(`${date}T00:00:00`);
    const day = (d.getDay() + 6) % 7;
    const monday = new Date(d);
    monday.setDate(d.getDate() - day);
    return monday;
}

function shiftWeek(delta: number): void {
    const monday = currentMonday(cursor.value);
    monday.setDate(monday.getDate() + delta * 7);
    cursor.value = iso(monday);
}

function goToToday(): void {
    cursor.value = props.anchorDate;
}

async function refresh(): Promise<void> {
    const monday = currentMonday(cursor.value);
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    await Promise.all([
        week.loadWeek(iso(monday)),
        week.loadRange(iso(monday), iso(sunday)),
    ]);
}

onMounted(refresh);
watch(cursor, refresh);

const totalScheduled = computed(() => week.weekDays.reduce((sum, d) => sum + d.scheduled_minutes, 0));
const totalTasks = computed(() => week.weekDays.reduce((sum, d) => sum + d.task_count, 0));
const overloadedDays = computed(() => week.weekDays.filter((d) => d.scheduled_minutes > 720));

const eventsByDay = computed<Record<string, ScheduleRangeEvent[]>>(() => {
    const map: Record<string, ScheduleRangeEvent[]> = {};
    for (const e of week.rangeEvents) {
        (map[e.assignment.date] ??= []).push(e);
    }
    return map;
});

const deadlinesByDay = computed<Record<string, ScheduleRangeEvent[]>>(() => {
    const map: Record<string, ScheduleRangeEvent[]> = {};
    for (const e of week.rangeEvents) {
        if (e.task?.due_at) {
            const due = e.task.due_at.slice(0, 10);
            (map[due] ??= []).push(e);
        }
    }
    return map;
});

function dayDeadlines(date: string): ScheduleRangeEvent[] {
    return deadlinesByDay.value[date] ?? [];
}

function isOverloaded(date: string): boolean {
    const day = week.weekDays.find((d) => d.date === date);
    return day !== undefined && day.scheduled_minutes > 720;
}
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="week-view">
        <!-- Header + navigation -->
        <header class="flex items-center justify-between">
            <h1 class="text-xl font-semibold" data-testid="week-title">Week</h1>
            <div class="flex items-center gap-2 text-sm">
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="week-prev" @click="shiftWeek(-1)">‹</button>
                <span data-testid="week-range">{{ week.weekStart }} – {{ week.weekEnd }}</span>
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="week-next" @click="shiftWeek(1)">›</button>
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="week-today" @click="goToToday">Today</button>
            </div>
        </header>

        <!-- Workload summary -->
        <section class="flex gap-4 text-sm" data-testid="week-summary">
            <span class="rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-1">Tasks: {{ totalTasks }}</span>
            <span class="rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-1">Scheduled: {{ totalScheduled }}m</span>
            <span
                v-if="overloadedDays.length > 0"
                class="rounded-sm bg-[#fff2f2] dark:bg-[#1D0002] text-[#F53003] px-2 py-1"
                data-testid="week-overload"
            >
                {{ overloadedDays.length }} overloaded day(s)
            </span>
        </section>

        <div v-if="week.loading" class="text-sm text-gray-500" data-testid="week-loading">Loading week…</div>
        <div v-if="week.error" class="text-sm text-[#F53003]" role="alert" data-testid="week-error">{{ week.error.message }}</div>

        <!-- Day columns -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3" data-testid="week-days">
            <div
                v-for="day in week.weekDays"
                :key="day.date"
                class="border border-gray-300 dark:border-gray-600 rounded-sm p-2"
                :class="isOverloaded(day.date) ? 'bg-[#fff2f2] dark:bg-[#1D0002]' : ''"
                data-testid="week-day"
            >
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ weekdays[day.weekday - 1] }} {{ day.date.slice(8) }}</div>
                <div class="text-sm font-semibold" data-testid="week-day-tasks">{{ day.task_count }} task(s)</div>
                <div class="text-xs text-gray-600 dark:text-gray-400">{{ day.scheduled_minutes }}m</div>

                <!-- Deadlines due that day -->
                <div v-if="dayDeadlines(day.date).length > 0" class="mt-1 text-xs text-[#F53003]" data-testid="week-deadlines">
                    {{ dayDeadlines(day.date).length }} due
                </div>

                <!-- Assignments -->
                <ul class="mt-1 text-xs text-gray-700 dark:text-gray-300 space-y-0.5" data-testid="week-assignments">
                    <li v-for="e in eventsByDay[day.date] ?? []" :key="e.assignment.id">
                        {{ e.task?.title ?? 'Untitled' }}
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
