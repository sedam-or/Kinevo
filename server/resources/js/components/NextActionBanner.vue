<script setup lang="ts">
/**
 * Next-action strip (TASK-P17-016). One suggested action per object state,
 * resolved by next-action.ts. Emits the action id so each surface decides
 * how to fulfill it.
 */
import type { NextAction } from '../next-action';

defineProps<{
    action: NextAction;
}>();

defineEmits<{
    (e: 'act', id: NextAction['id']): void;
}>();
</script>

<template>
    <div
        class="flex items-center justify-between gap-3 rounded-sm border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm"
        data-testid="next-action"
        :data-action="action.id"
    >
        <span class="text-gray-700 dark:text-gray-300">{{ action.label }}</span>
        <button
            type="button"
            class="shrink-0 underline underline-offset-2 text-[var(--color-primary)] hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
            data-testid="next-action-button"
            @click="$emit('act', action.id)"
        >
            Do it →
        </button>
    </div>
</template>
