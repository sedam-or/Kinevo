<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useAnalyticsStore } from './store';
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