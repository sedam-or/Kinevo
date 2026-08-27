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
    <dl class="mt-2 border-t border-border/20 pt-2 text-xs text-text-muted" :data-testid="`interpretation-${id}`">
        <div class="flex flex-col gap-1">
            <div class="flex flex-wrap gap-1" :data-testid="`interpretation-${id}-changed`">
                <dt class="font-medium uppercase tracking-wide text-text-muted">What changed</dt>
                <dd>{{ interpretation.whatChanged }}</dd>
            </div>
            <div class="flex flex-wrap gap-1" :data-testid="`interpretation-${id}-why`">
                <dt class="font-medium uppercase tracking-wide text-text-muted">Why it matters</dt>
                <dd>{{ interpretation.whyItMatters }}</dd>
            </div>
            <div class="flex flex-wrap gap-1" :data-testid="`interpretation-${id}-do`">
                <dt class="font-medium uppercase tracking-wide text-text-muted">What to do</dt>
                <dd>{{ interpretation.whatToDo }}</dd>
            </div>
        </div>
    </dl>
</template>