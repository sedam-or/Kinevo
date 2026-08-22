<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useWeekStore } from './store';

const props = defineProps<{
    anchorDate: string;
}>();

const week = useWeekStore();

const cursor = ref(props.anchorDate.slice(0, 7));

function shiftMonth(delta: number): void {
    const [y, m] = cursor.value.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    cursor.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function goToThisMonth(): void {
    cursor.value = props.anchorDate.slice(0, 7);
}

async function refresh(): Promise<void> {
    await week.loadCalendar(cursor.value);
}

onMounted(refresh);
watch(cursor, refresh);

const cells = computed<{ date: string; day: number | null; task_count: number }[]>(() => {
    const [y, m] = cursor.value.split('-').map(Number);
    const firstWeekday = (new Date(y, m - 1, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(y, m, 0).getDate();
    const lookup = new Map(week.calendarDays.map((d) => [d.date, d.task_count]));

    const cells: { date: string; day: number | null; task_count: number }[] = [];
    for (let i = 0; i < firstWeekday; i++) {
        cells.push({ date: '', day: null, task_count: 0 });
    }
    for (let day = 1; day <= daysInMonth; day++) {
        const date = `${y}-${String(m).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        cells.push({ date, day, task_count: lookup.get(date) ?? 0 });
    }
    return cells;
});
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="calendar-view">
        <header class="flex items-center justify-between">
            <h1 class="text-xl font-semibold" data-testid="calendar-title">Calendar</h1>
            <div class="flex items-center gap-2 text-sm">
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="calendar-prev" @click="shiftMonth(-1)">‹</button>
                <span data-testid="calendar-month">{{ week.calendarYear }}-{{ String(week.calendarMonth).padStart(2, '0') }}</span>
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="calendar-next" @click="shiftMonth(1)">›</button>
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="calendar-today" @click="goToThisMonth">This month</button>
            </div>
        </header>

        <div v-if="week.loading" class="text-sm text-gray-500" data-testid="calendar-loading">Loading calendar…</div>
        <div v-if="week.error" class="text-sm text-danger" role="alert" data-testid="calendar-error">{{ week.error.message }}</div>

        <div class="grid grid-cols-7 gap-1 text-center text-xs text-gray-500 dark:text-gray-400">
            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
        </div>

        <section class="grid grid-cols-7 gap-1" data-testid="calendar-grid">
            <div
                v-for="(cell, i) in cells"
                :key="i"
                class="aspect-square border border-gray-200 dark:border-gray-700 rounded-sm p-1 text-sm"
                :class="cell.day !== null && cell.task_count > 0 ? 'bg-gray-100 dark:bg-gray-800' : ''"
                data-testid="calendar-cell"
            >
                <template v-if="cell.day !== null">
                    <div>{{ cell.day }}</div>
                    <div v-if="cell.task_count > 0" class="text-[10px] text-gray-600 dark:text-gray-400" data-testid="calendar-task-count">
                        {{ cell.task_count }} task(s)
                    </div>
                </template>
            </div>
        </section>
    </div>
</template>
