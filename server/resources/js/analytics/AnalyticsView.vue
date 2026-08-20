<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useAnalyticsStore } from './store';
import type { DeadlineHealth } from './types';
import type { ApiError } from '../api/types';

const analytics = useAnalyticsStore();

type RangePreset = '7d' | '30d' | 'week' | 'month';

const presets: { key: RangePreset; label: string }[] = [
    { key: '7d', label: '7 days' },
    { key: '30d', label: '30 days' },
    { key: 'week', label: 'This week' },
    { key: 'month', label: 'This month' },
];

const activePreset = ref<RangePreset>('7d');

function formatDate(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function rangeFor(preset: RangePreset): { from: string; to: string } {
    const to = new Date();
    const from = new Date();
    if (preset === '7d') {
        from.setDate(to.getDate() - 6);
    } else if (preset === '30d') {
        from.setDate(to.getDate() - 29);
    } else if (preset === 'week') {
        const day = to.getDay(); // 0 = Sunday
        from.setDate(to.getDate() - day);
    } else {
        from.setDate(1);
    }
    return { from: formatDate(from), to: formatDate(to) };
}

async function load(preset: RangePreset): Promise<void> {
    activePreset.value = preset;
    const { from, to } = rangeFor(preset);
    await analytics.load(from, to);
}

const workPercent = computed(() => Math.round(analytics.workRatio * 100));
const rechargePercent = computed(() => Math.round(analytics.rechargeRatio * 100));

const bandLabel = computed(() => {
    switch (analytics.band) {
        case 'work_leaning':
            return 'Mostly work';
        case 'recharge_leaning':
            return 'Mostly recharge';
        case 'balanced':
            return 'Balanced';
        default:
            return 'No data yet';
    }
});

const goalHealthLabel = computed(() => {
    const h = analytics.goalDeadlineHealth;
    const labelled: [DeadlineHealth, string][] = [
        ['on_track', 'On track'],
        ['at_risk', 'At risk'],
        ['overdue', 'Overdue'],
        ['completed', 'Completed'],
    ];
    return labelled
        .filter(([key]) => h[key] > 0)
        .map(([key, label]) => `${label} ${h[key]}`)
        .join(' · ');
});

function deadlineLabel(health: DeadlineHealth, daysRemaining: number | null): string {
    switch (health) {
        case 'completed':
            return 'Completed';
        case 'overdue':
            return `Overdue ${Math.abs(daysRemaining ?? 0)}d`;
        case 'at_risk':
            return `At risk · ${daysRemaining ?? 0}d left`;
        case 'on_track':
            return `On track · ${daysRemaining ?? 0}d left`;
        default:
            return 'No deadline';
    }
}

const recommendationLabel = computed(() => {
    switch (analytics.capacityRecommendation) {
        case 'BOOST_AVAILABLE':
            return 'Boost / backlog available';
        case 'REDUCE_LOAD':
            return 'Reduce next load';
        default:
            return 'Maintain';
    }
});

function minutesLabel(minutes: number): string {
    if (minutes < 60) {
        return `${minutes}m`;
    }
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

onMounted(() => {
    void load(activePreset.value);
});

onUnmounted(() => {
    analytics.clear();
});

function run(fn: () => Promise<void>): void {
    void fn();
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-2" data-testid="analytics-presets">
            <button
                v-for="preset in presets"
                :key="preset.key"
                type="button"
                class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                :class="activePreset === preset.key ? 'bg-gray-200 dark:bg-gray-700' : ''"
                :disabled="analytics.loading"
                data-testid="analytics-preset"
                @click="run(() => load(preset.key))"
            >
                {{ preset.label }}
            </button>
        </div>

        <div v-if="analytics.loading" class="text-sm text-gray-500 dark:text-gray-400" data-testid="analytics-loading">
            Loading…
        </div>

        <div v-else-if="analytics.error" class="text-sm text-[#F53003]" role="alert" data-testid="analytics-error">
            {{ (analytics.error as ApiError).message }}
        </div>

        <div v-else-if="!analytics.hasData" class="text-sm text-gray-500 dark:text-gray-400" data-testid="analytics-empty">
            No tracked time in this period yet.
        </div>

        <template v-else>
            <div class="border border-gray-300 dark:border-gray-700 rounded-sm p-3" data-testid="analytics-summary">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Work-Life Ratio</div>
                <div class="text-lg font-semibold" data-testid="analytics-ratio">
                    Work {{ workPercent }}% · Recharge {{ rechargePercent }}%
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400" data-testid="analytics-band">{{ bandLabel }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ minutesLabel(analytics.productiveMinutes) }} focus · {{ minutesLabel(analytics.rechargeMinutes) }} recharge
                </div>
                <div class="mt-2 flex h-3 w-full overflow-hidden rounded-sm bg-gray-200 dark:bg-gray-700" data-testid="analytics-bar">
                    <div class="bg-[#F53003]" :style="{ width: `${workPercent}%` }" data-testid="analytics-work-segment" />
                    <div class="bg-green-600" :style="{ width: `${rechargePercent}%` }" data-testid="analytics-recharge-segment" />
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-testid="analytics-disclaimer">
                    {{ analytics.disclaimer }}
                </p>
            </div>

            <div v-if="analytics.hasGoals" class="border border-gray-300 dark:border-gray-700 rounded-sm p-3" data-testid="analytics-goals">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Goal progress</div>

                <div class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-sm" data-testid="analytics-goal-summary">
                    <span class="text-gray-600 dark:text-gray-400">
                        {{ Math.round(analytics.goalCompletionRate * 100) }}% complete
                    </span>
                    <span v-if="goalHealthLabel" class="text-gray-500 dark:text-gray-400" data-testid="analytics-goal-health">{{ goalHealthLabel }}</span>
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ analytics.goalCompletedMilestones }}/{{ analytics.goalTotalMilestones }} milestones
                    </span>
                    <span v-if="analytics.goalWorkloadCompletion > 0" class="text-gray-500 dark:text-gray-400">
                        {{ Math.round(analytics.goalWorkloadCompletion * 100) }}% tasks completed
                    </span>
                </div>

                <ul class="space-y-2">
                    <li v-for="goal in analytics.goals" :key="goal.id" class="text-sm" data-testid="analytics-goal">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ goal.title }}</span>
                            <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400" data-testid="analytics-goal-deadline">
                                {{ deadlineLabel(goal.deadline_health, goal.days_remaining) }}
                            </span>
                        </div>
                        <div class="mt-1 flex h-2 w-full overflow-hidden rounded-sm bg-gray-200 dark:bg-gray-700">
                            <div class="bg-[#F53003]" :style="{ width: `${goal.progress}%` }" data-testid="analytics-goal-bar" />
                        </div>
                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ goal.progress }}% · {{ goal.milestones_completed }}/{{ goal.milestones_total }} milestones · {{ goal.tasks_completed }}/{{ goal.tasks_total }} tasks
                        </div>
                    </li>
                </ul>

                <div v-if="analytics.programs.length > 0" class="mt-3 border-t border-gray-200 dark:border-gray-700 pt-2">
                    <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Programs</div>
                    <ul class="space-y-1">
                        <li v-for="program in analytics.programs" :key="program.id" class="flex items-center justify-between text-sm" data-testid="analytics-program">
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ program.name }}</span>
                            <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                {{ Math.round(program.workload_completion * 100) }}% · {{ program.tasks_completed }}/{{ program.tasks_total }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-if="analytics.capacityDays.length > 0" class="border border-gray-300 dark:border-gray-700 rounded-sm p-3" data-testid="analytics-capacity">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Capacity</div>

                <div class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-sm" data-testid="analytics-capacity-summary">
                    <span class="text-gray-600 dark:text-gray-400">
                        {{ Math.round(analytics.capacityRealization * 100) }}% realized
                    </span>
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ analytics.capacityDays.filter((d) => d.status === 'overload').length }} overloaded days
                    </span>
                    <span class="text-gray-500 dark:text-gray-400">{{ recommendationLabel }} ({{ analytics.capacityConfidence }})</span>
                </div>

                <ul class="space-y-1">
                    <li v-for="day in analytics.capacityDays" :key="day.date" class="flex items-center gap-2 text-sm" data-testid="analytics-capacity-day">
                        <span class="w-24 shrink-0 text-gray-600 dark:text-gray-400">{{ day.date }}</span>
                        <div class="flex h-2.5 flex-1 overflow-hidden rounded-sm bg-gray-200 dark:bg-gray-700">
                            <div
                                class="h-full"
                                :class="day.status === 'overload' ? 'bg-red-500' : 'bg-[#F53003]'"
                                :style="{ width: `${Math.min(100, (day.scheduled_minutes / Math.max(1, day.available_minutes + day.scheduled_minutes)) * 100)}%` }"
                                data-testid="analytics-capacity-load"
                            />
                        </div>
                        <span class="w-32 shrink-0 text-right text-xs" :class="day.status === 'overload' ? 'text-[#F53003]' : 'text-gray-500 dark:text-gray-400'">
                            {{ minutesLabel(day.scheduled_minutes) }}{{ day.status === 'overload' ? ` / ${minutesLabel(day.overload_minutes)} overload` : '' }}
                        </span>
                    </li>
                </ul>

                <div v-if="analytics.capacityWeeks.length > 0" class="mt-3 border-t border-gray-200 dark:border-gray-700 pt-2">
                    <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Trend</div>
                    <ul class="space-y-1">
                        <li v-for="week in analytics.capacityWeeks" :key="week.week_start" class="flex items-center justify-between text-sm" data-testid="analytics-capacity-week">
                            <span class="text-gray-600 dark:text-gray-400">Week of {{ week.week_start }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ Math.round(week.realization * 100) }}% · {{ minutesLabel(week.completed_minutes) }} / {{ minutesLabel(week.planned_minutes) }}
                            </span>
                        </li>
                    </ul>
                </div>

                <p v-if="analytics.capacityReason" class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-testid="analytics-capacity-reason">
                    {{ analytics.capacityReason }}
                </p>
            </div>

            <div v-if="analytics.days.length > 0" class="border border-gray-300 dark:border-gray-700 rounded-sm p-3" data-testid="analytics-days">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Per day</div>
                <ul class="space-y-1">
                    <li
                        v-for="day in analytics.days"
                        :key="day.date"
                        class="flex items-center gap-2 text-sm"
                        data-testid="analytics-day"
                    >
                        <span class="w-24 shrink-0 text-gray-600 dark:text-gray-400">{{ day.date }}</span>
                        <div class="flex h-2.5 flex-1 overflow-hidden rounded-sm bg-gray-200 dark:bg-gray-700">
                            <div class="bg-[#F53003]" :style="{ width: `${Math.round(day.work_ratio * 100)}%` }" data-testid="analytics-day-work" />
                            <div class="bg-green-600" :style="{ width: `${Math.round(day.recharge_ratio * 100)}%` }" data-testid="analytics-day-recharge" />
                        </div>
                        <span class="w-28 shrink-0 text-right text-xs text-gray-500 dark:text-gray-400">
                            {{ minutesLabel(day.productive_minutes) }} / {{ minutesLabel(day.recharge_minutes) }}
                        </span>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>