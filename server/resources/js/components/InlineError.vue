<script setup lang="ts">
/**
 * Data-load error with a recovery path (P28-011 / RET-013). Uniform pattern:
 * message explains what happened, "Try again" re-runs the surface's own load
 * action — the same data is preserved server-side, so retrying is safe.
 */
defineProps<{ message: string }>();

const emit = defineEmits<{
    (e: 'retry'): void;
}>();
</script>

<template>
    <div class="text-sm text-danger flex items-center gap-3" role="alert" data-testid="inline-error">
        <span>{{ message }}</span>
        <button
            type="button"
            class="text-xs underline hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus rounded-sm"
            data-testid="inline-error-retry"
            @click="emit('retry')"
        >
            Try again
        </button>
    </div>
</template>
