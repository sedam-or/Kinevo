<script setup lang="ts">
import { computed } from 'vue';
import { useShellStore } from './store';
import AppShell from './AppShell.vue';
import AppErrorBoundary from './AppErrorBoundary.vue';

const shell = useShellStore();

const viewTitle = computed(() => {
    const item = shell.navItems.find((i) => i.key === shell.activeView);
    return item ? item.label : 'Kinevo';
});
</script>

<template>
    <AppErrorBoundary>
        <AppShell>
            <!-- Global loading state -->
            <div v-if="shell.isLoading" class="text-sm text-text-muted" data-testid="global-loading">
                Loading…
            </div>

            <!-- Placeholder content surface; real views land in later tasks -->
            <div data-testid="view-content">
                <h1 class="text-xl font-semibold mb-4">{{ viewTitle }}</h1>
                <p class="text-sm text-text-muted">
                    The {{ shell.activeView }} view is wired into the shell and will be
                    implemented in its dedicated task.
                </p>
            </div>
        </AppShell>
    </AppErrorBoundary>
</template>
