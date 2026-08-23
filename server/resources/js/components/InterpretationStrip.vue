<script setup lang="ts">
/**
 * "What changed / Why it matters / What to do" strip (TASK-P17-017,
 * design.md §38/§104 P17-G). Keeps every chart actionable — no decorative
 * chart stands alone. Copy is derived deterministically by
 * analytics/interpretation.ts and passed in.
 */
import type { Interpretation } from '../analytics/interpretation';

defineProps<{
    interpretation: Interpretation;
    /** Stable id used to build data-testid suffixes. */
    id: string;
}>();
</script>

<template>
    <dl class="mt-2 border-t border-gray-200 dark:border-gray-700 pt-2 text-xs text-gray-600 dark:text-gray-400" :data-testid="`interpretation-${id}`">
        <div class="flex flex-col gap-1">
            <div class="flex flex-wrap gap-1" :data-testid="`interpretation-${id}-changed`">
                <dt class="font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">What changed</dt>
                <dd class="text-gray-600 dark:text-gray-400">{{ interpretation.whatChanged }}</dd>
            </div>
            <div class="flex flex-wrap gap-1" :data-testid="`interpretation-${id}-why`">
                <dt class="font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Why it matters</dt>
                <dd class="text-gray-600 dark:text-gray-400">{{ interpretation.whyItMatters }}</dd>
            </div>
            <div class="flex flex-wrap gap-1" :data-testid="`interpretation-${id}-do`">
                <dt class="font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">What to do</dt>
                <dd class="text-gray-600 dark:text-gray-400">{{ interpretation.whatToDo }}</dd>
            </div>
        </div>
    </dl>
</template>