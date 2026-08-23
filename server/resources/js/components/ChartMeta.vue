<script setup lang="ts">
/**
 * Chart metadata line (TASK-P17-019, design.md §38 Analytics cards):
 * exposes period and unit up front, plus color legend swatches so every chart
 * is self-describing — title + metric + period + unit + baseline + trend +
 * legend + context, with no pie for productivity data.
 */
defineProps<{
    /** Data testid suffix, e.g. `summary`. */
    id: string;
    /** Rendered period, e.g. "2026-08-14 – 2026-08-20". */
    period: string;
    /** Unit descriptor for the chart's metric. */
    unit: string;
    /** Color legend: tailwind swatch class + human label. */
    legend: { swatch: string; label: string }[];
}>();
</script>

<template>
    <div
        class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400"
        :data-testid="`chart-meta-${id}`"
    >
        <span :data-testid="`chart-period-${id}`">
            Period <span class="text-gray-600 dark:text-gray-300">{{ period }}</span>
        </span>
        <span aria-hidden="true">·</span>
        <span :data-testid="`chart-unit-${id}`">{{ unit }}</span>
        <span
            v-for="item in legend"
            :key="item.label"
            class="inline-flex items-center gap-1"
            :data-testid="`chart-legend-${id}`"
        >
            <span class="inline-block h-2.5 w-2.5 rounded-sm" :class="item.swatch" aria-hidden="true" />
            <span>{{ item.label }}</span>
        </span>
    </div>
</template>