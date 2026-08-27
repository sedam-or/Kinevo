<script setup lang="ts">
import { useShellStore } from '../shell/store';
import KButton from '../components/KButton.vue';

/**
 * TASK-P17-028 (Settings discoverability): shown by AI-dependent actions when
 * the canonical server state is disabled/not_configured. Routes the user to
 * Settings → AI & Providers instead of a dead-end error.
 */
const shell = useShellStore();
</script>

<template>
    <p class="text-sm text-text-muted flex items-center gap-2 flex-wrap" data-testid="ai-not-configured" role="status">
        <span>AI is not configured.</span>
        <KButton
            variant="secondary"
            class="!min-h-0 !px-3 !py-1 text-sm"
            data-testid="configure-ai"
            @click="shell.setView('ai-settings')"
        >
            Configure AI
        </KButton>
    </p>
</template>
