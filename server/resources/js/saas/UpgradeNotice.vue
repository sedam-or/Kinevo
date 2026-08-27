<script setup lang="ts">
/**
 * TASK-P23-008 — limit/upgrade UX. Explains what is limited, current usage
 * where relevant, which plan enables it, and the next action.
 *
 * Uses the app's one `.surface-hero` call-to-decision slot per page
 * (docs/design-tokens.md §4a) instead of ad-hoc amber washes.
 */
import KButton from '../components/KButton.vue';

defineProps<{
    message: string;
    entitlement?: string;
    plan?: string;
}>();
const emit = defineEmits<{ (e: 'open-plans'): void }>();
</script>

<template>
    <div class="surface-hero p-3 text-sm flex flex-col gap-2" role="alert" data-testid="upgrade-notice">
        <p class="font-medium">{{ message }}</p>
        <p class="text-xs text-text-muted">
            Current plan: <span class="font-medium">{{ plan ?? 'free' }}</span>
            <span v-if="entitlement"> · limited by {{ entitlement.replaceAll('_', ' ') }}</span>
        </p>
        <KButton variant="primary" class="self-start" data-testid="upgrade-open-plans" @click="emit('open-plans')">
            View plans & upgrade
        </KButton>
    </div>
</template>
