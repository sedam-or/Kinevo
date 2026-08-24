<script setup lang="ts">
import { useShellStore } from '../shell/store';

/**
 * TASK-P17-028 (Settings discoverability): shown by AI-dependent actions when
 * the canonical server state is disabled/not_configured. Routes the user to
 * Settings → AI & Providers instead of a dead-end error.
 */
const shell = useShellStore();
</script>

<template>
    <p class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-2 flex-wrap" data-testid="ai-not-configured" role="status">
        <span>AI is not configured.</span>
        <button
            type="button"
            class="text-sm border border-[var(--color-primary)] text-[var(--color-primary)] rounded-sm px-3 py-1"
            data-testid="configure-ai"
            @click="shell.setView('ai-settings')"
        >
            Configure AI
        </button>
    </p>
</template>
