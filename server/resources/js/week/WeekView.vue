<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import WhyThis from '../components/WhyThis.vue';
import KIcon from '../components/KIcon.vue';
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
                <button type="button" class="border border-border rounded-sm px-2 py-1 hover:bg-surface" data-testid="week-prev" aria-label="Previous page" @click="shiftWeek(-1)"><KIcon name="chevron-left" :size="16" /></button>
                <span data-testid="week-range">{{ week.weekStart }} – {{ week.weekEnd }}</span>
                <button type="button" class="border border-border rounded-sm px-2 py-1 hover:bg-surface" data-testid="week-next" aria-label="Next page" @click="shiftWeek(1)"><KIcon name="chevron-right" :size="16" /></button>
                <button type="button" class="border border-border rounded-sm px-2 py-1 hover:bg-surface" data-testid="week-today" @click="goToToday">Today</button>
            </div>
        </header>

        <!-- Workload summary -->
        <section class="flex gap-4 text-sm" data-testid="week-summary">
            <span class="rounded-sm bg-surface border border-border px-2 py-1">Tasks: {{ totalTasks }}</span>
            <span class="rounded-sm bg-surface border border-border px-2 py-1">Scheduled: {{ totalScheduled }}m</span>
            <span
                v-if="overloadedDays.length > 0"
                class="rounded-sm bg-danger-tint text-danger px-2 py-1"
                data-testid="week-overload"
            >
                {{ overloadedDays.length }} overloaded day(s)
            </span>
        </section>

        <div v-if="week.loading" class="text-sm text-text-muted" data-testid="week-loading">Loading week…</div>
        <div v-if="week.error" class="text-sm text-danger border border-danger rounded-sm p-3" role="alert" data-testid="week-error">
            <span class="font-medium">We couldn't load your schedule.</span>
            <span> {{ week.error.message }} Nothing is changed in your plan; retry when you're online.</span>
        </div>

        <!-- Day columns -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3" data-testid="week-days">
            <div
                v-for="day in week.weekDays"
                :key="day.date"
                class="border border-border/30 rounded-sm p-2"
                :class="[isOverloaded(day.date) ? 'bg-danger-tint' : '', day.date === anchorDate ? 'border-2 border-primary' : '']"
                data-testid="week-day"
            >
                <div class="text-xs font-medium text-text-muted">{{ weekdays[day.weekday - 1] }} {{ day.date.slice(8) }}</div>
                <div class="text-sm font-semibold" data-testid="week-day-tasks">{{ day.task_count }} task(s)</div>
                <div class="text-xs text-text-muted">{{ day.scheduled_minutes }}m</div>

                <!-- Deadlines due that day -->
                <div v-if="dayDeadlines(day.date).length > 0" class="mt-1 text-xs text-danger" data-testid="week-deadlines">
                    {{ dayDeadlines(day.date).length }} due
                </div>

                <!-- Assignments -->
                <ul class="mt-1 text-xs text-text space-y-1" data-testid="week-assignments">
                    <li v-for="e in eventsByDay[day.date] ?? []" :key="e.assignment.id" class="flex flex-col gap-0.5">
                        {{ e.task?.title ?? 'Untitled' }}
                        <!-- FR-63 (P17-015): per-placement explanation, collapsed. -->
                        <WhyThis
                            v-if="e.task"
                            :task="{ priority_tier: e.task.priority_tier, due_at: e.task.due_at, estimated_minutes: e.task.estimated_minutes }"
                            :assignment="e.assignment"
                        />
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
