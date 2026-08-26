<script setup lang="ts">
/**
 * TASK-P23-008 — limit/upgrade UX. Explains what is limited, current usage
 * where relevant, which plan enables it, and the next action.
 */
defineProps<{
    message: string;
    entitlement?: string;
    plan?: string;
}>();
const emit = defineEmits<{ (e: 'open-plans'): void }>();
</script>

<template>
    <div class="rounded-sm border border-warning dark:border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 p-3 text-sm flex flex-col gap-2" role="alert" data-testid="upgrade-notice">
        <p class="font-medium">{{ message }}</p>
        <p class="text-xs text-gray-600 dark:text-gray-400">
            Current plan: <span class="font-medium">{{ plan ?? 'free' }}</span>
            <span v-if="entitlement"> · limited by {{ entitlement.replaceAll('_', ' ') }}</span>
        </p>
        <button type="button" class="self-start underline min-h-[44px]" data-testid="upgrade-open-plans" @click="emit('open-plans')">
            View plans & upgrade
        </button>
    </div>
</template>
