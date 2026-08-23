<script setup lang="ts">
/**
 * "Why this task now?" (TASK-P17-015, FR-63 explainable decisions).
 *
 * Expandable explanation derived deterministically from observable fields the
 * scheduler already uses — priority tier, deadline proximity, slot fit,
 * optional energy signal. No new scheduling logic lives here (task notes:
 * reuse existing explainability). Collapsed by default so cards stay
 * uncluttered (design.md §104).
 */
import { computed, ref } from 'vue';

const props = defineProps<{
    task: { priority_tier?: number | null; due_at?: string | null; estimated_minutes?: number | null };
    assignment: { start_at: string; duration_minutes?: number | null; locked?: boolean; source?: string | null };
    /** Optional human note from the adaptive context store (energy fit). */
    energyNote?: string | null;
}>();

const open = ref(false);

function toggle(): void {
    open.value = !open.value;
}

const tierLine = computed(() => {
    const tier = props.task.priority_tier ?? 3;
    const weight = tier === 1 ? 'highest' : tier === 2 ? 'high' : 'standard';
    return `Priority tier P${tier} gives it ${weight} scheduling weight.`;
});

const deadlineLine = computed(() => {
    if (!props.task.due_at) {
        return 'No deadline pressure — ordered by priority and fit.';
    }
    const due = new Date(props.task.due_at);
    const dayMs = 24 * 60 * 60 * 1000;
    const startOfDay = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    const days = Math.round((startOfDay(due) - startOfDay(new Date())) / dayMs);
    const dateLabel = due.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    if (days < 0) {
        return `Due ${dateLabel} — overdue by ${Math.abs(days)} day(s), so it ranks high.`;
    }
    if (days === 0) {
        return `Due ${dateLabel} — due today.`;
    }
    return `Due ${dateLabel} — ${days} day(s) away, deadline proximity considered.`;
});

const slotLine = computed(() => {
    if (props.assignment.locked) {
        return 'Locked anchor — automation cannot move this placement.';
    }
    const planned = props.assignment.duration_minutes ?? null;
    const estimate = props.task.estimated_minutes ?? null;
    if (planned !== null && estimate !== null && planned === estimate) {
        return `Placed in a ${planned}m slot that matches its ${estimate}m estimate.`;
    }
    if (planned !== null) {
        return `Placed in a ${planned}m slot fitting the free capacity.`;
    }
    return 'Placed in a slot fitting the free capacity.';
});
</script>

<template>
    <div class="text-xs">
        <button
            type="button"
            class="text-gray-500 dark:text-gray-400 underline underline-offset-2 hover:text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
            :aria-expanded="open"
            data-testid="why-this-toggle"
            @click="toggle"
        >
            {{ open ? 'Hide why' : 'Why this task now?' }}
        </button>
        <ul v-if="open" class="mt-1 space-y-0.5 text-gray-600 dark:text-gray-400" data-testid="why-this-content">
            <li data-testid="why-tier">{{ tierLine }}</li>
            <li data-testid="why-deadline">{{ deadlineLine }}</li>
            <li data-testid="why-slot">{{ slotLine }}</li>
            <li v-if="energyNote" data-testid="why-energy">{{ energyNote }}</li>
        </ul>
    </div>
</template>
