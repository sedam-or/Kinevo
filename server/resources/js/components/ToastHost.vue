<script setup lang="ts">
/**
 * Toast outlet (TASK-P17-011). Fixed stack, polite live region so completed
 * actions are announced without stealing focus.
 */
import { useToastStore } from './toast';
import KIcon from './KIcon.vue';

const toast = useToastStore();
</script>

<template>
    <div
        v-if="toast.items.length"
        class="fixed bottom-4 right-4 z-[var(--z-toast)] flex flex-col gap-2"
        role="status"
        aria-live="polite"
        data-testid="toast-host"
    >
        <div
            v-for="item in toast.items"
            :key="item.id"
            class="border border-border bg-bg rounded-sm shadow-rest px-3 py-2 text-sm flex items-center gap-3"
            :data-testid="`toast-${item.id}`"
        >
            <span>{{ item.message }}</span>
            <button
                type="button"
                class="text-xs text-text-muted hover:text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm px-1"
                :data-testid="`toast-dismiss-${item.id}`"
                aria-label="Dismiss notification"
                @click="toast.dismiss(item.id)"
            >
                <KIcon name="x-mark" :size="14" />
            </button>
        </div>
    </div>
</template>
