<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useWorkspaceStore } from './store';
import { useGoalStore } from '../goal/store';
import { useShellStore } from '../shell/store';
import KButton from '../components/KButton.vue';

/**
 * Workspace Home (TASK-P19-038) — identity-first context surface, ordered:
 * Identity → Current Goal (with progress + next action) → Today →
 * Knowledge → Canvas. Deliberately NOT a metric wall: every section is a
 * doorway into the workspace's actual work.
 */
const workspaces = useWorkspaceStore();
const goals = useGoalStore();
const shell = useShellStore();

const ws = computed(() => workspaces.activeWorkspace);

/** Current goal = first non-terminal goal in this workspace. */
const currentGoal = computed(() => {
    const terminal = new Set(['completed', 'archived', 'dropped']);
    return goals.goals.find((g) => !terminal.has(g.status)) ?? null;
});

onMounted(async () => {
    await Promise.all([workspaces.load(), goals.loadAll()]);
});

function go(view: Parameters<typeof shell.setView>[0], focusId?: number): void {
    shell.setView(view, focusId);
}
</script>

<template>
    <div class="max-w-3xl flex flex-col gap-6" data-testid="workspace-home">
        <!-- 1 · Identity -->
        <header class="flex items-center gap-3" data-testid="wh-identity">
            <span
                class="inline-flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold"
                :style="{ backgroundColor: ws?.accent ?? 'var(--color-primary)', color: '#fff' }"
                data-testid="wh-icon"
            >{{ (ws?.name ?? 'W').slice(0, 1).toUpperCase() }}</span>
            <div>
                <h1 class="text-xl font-semibold" data-testid="wh-name">{{ ws?.name ?? 'Workspace' }}</h1>
                <p v-if="ws?.description" class="text-sm text-gray-500 dark:text-gray-400">{{ ws.description }}</p>
            </div>
        </header>

        <!-- 2 · Current Goal + progress + next action -->
        <section v-if="currentGoal" class="rounded-sm border border-gray-300 dark:border-gray-600 p-4 flex flex-col gap-2" data-testid="wh-current-goal">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Current goal</p>
            <h2 class="font-medium">{{ currentGoal.title }}</h2>
            <div class="h-2 rounded-sm bg-gray-100 dark:bg-gray-800 overflow-hidden" role="progressbar" :aria-valuenow="currentGoal.progress" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full bg-[var(--color-primary)]" :style="{ width: currentGoal.progress + '%' }" />
            </div>
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ currentGoal.progress }}% complete</span>
                <KButton variant="secondary" @click="go('goals', currentGoal.id)">Open goal</KButton>
            </div>
        </section>

        <!-- 3 · Next action / Today entry -->
        <section class="flex flex-col gap-2" data-testid="wh-today">
            <KButton variant="primary" @click="go('today')">Go to Today</KButton>
            <p class="text-xs text-gray-500 dark:text-gray-400">Today shows your global commitments — capture lands in {{ ws?.name }}.</p>
        </section>

        <!-- 4/5 · Knowledge & Canvas doorways -->
        <section class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-testid="wh-doorways">
            <button type="button" class="rounded-sm border border-gray-300 dark:border-gray-600 p-4 text-left min-h-[44px]" data-testid="wh-knowledge" @click="go('knowledge')">
                <span class="font-medium">Knowledge</span>
                <p class="text-xs text-gray-500 dark:text-gray-400">Notes in this workspace</p>
            </button>
            <button type="button" class="rounded-sm border border-gray-300 dark:border-gray-600 p-4 text-left min-h-[44px]" data-testid="wh-canvas" @click="go('canvas')">
                <span class="font-medium">Canvas</span>
                <p class="text-xs text-gray-500 dark:text-gray-400">Boards in this workspace</p>
            </button>
        </section>

        <!-- 6/7 · Upcoming → schedule; Progress → analytics -->
        <section class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-testid="wh-review">
            <button type="button" class="rounded-sm border border-gray-300 dark:border-gray-600 p-4 text-left min-h-[44px]" data-testid="wh-upcoming" @click="go('schedule')">
                <span class="font-medium">Upcoming</span>
                <p class="text-xs text-gray-500 dark:text-gray-400">Draft and apply the week ahead</p>
            </button>
            <button type="button" class="rounded-sm border border-gray-300 dark:border-gray-600 p-4 text-left min-h-[44px]" data-testid="wh-progress" @click="go('analytics')">
                <span class="font-medium">Progress</span>
                <p class="text-xs text-gray-500 dark:text-gray-400">What changed and why it matters</p>
            </button>
        </section>
    </div>
</template>
