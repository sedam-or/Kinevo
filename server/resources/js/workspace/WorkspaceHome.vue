<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useWorkspaceStore } from './store';
import { useGoalStore } from '../goal/store';
import { useShellStore } from '../shell/store';
import KButton from '../components/KButton.vue';
import KIcon from '../components/KIcon.vue';
import FeatureHelp from '../components/FeatureHelp.vue';

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
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border-2 border-border text-sm font-semibold"
                :style="{ backgroundColor: ws?.accent ?? 'var(--color-primary)', color: '#fff' }"
                data-testid="wh-icon"
            >{{ (ws?.name ?? 'W').slice(0, 1).toUpperCase() }}</span>
            <div>
                <h1 class="text-xl font-semibold flex items-center gap-2" data-testid="wh-name">
                    {{ ws?.name ?? 'Workspace' }}
                    <FeatureHelp id="workspace" title="Workspaces" body="Keep life areas separate — Research, Work, Personal — while Today still shows everything scheduled across all of them." />
                </h1>
                <p v-if="ws?.description" class="text-sm text-text-muted">{{ ws.description }}</p>
            </div>
        </header>

        <!-- 2 · Current Goal + progress + next action -->
        <section v-if="currentGoal" class="surface-secondary p-4 flex flex-col gap-2" data-testid="wh-current-goal">
            <p class="font-mono text-[11px] uppercase tracking-widest text-text-muted">Current goal</p>
            <h2 class="font-medium">{{ currentGoal.title }}</h2>
            <div class="h-2 rounded-sm bg-surface overflow-hidden" role="progressbar" :aria-valuenow="currentGoal.progress" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full bg-[var(--color-primary)]" :style="{ width: currentGoal.progress + '%' }" />
            </div>
            <div class="flex items-center justify-between text-xs text-text-muted">
                <span>{{ currentGoal.progress }}% complete</span>
                <KButton variant="secondary" @click="go('goals', currentGoal.id)">Open goal</KButton>
            </div>
        </section>

        <!-- 3 · Next action / Today entry -->
        <section class="flex flex-col gap-2" data-testid="wh-today">
            <KButton variant="primary" @click="go('today')">Go to Today</KButton>
            <p class="text-xs text-text-muted">Today shows your global commitments — capture lands in {{ ws?.name }}.</p>
        </section>

        <!-- 4/5 · Knowledge & Canvas doorways -->
        <section class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-testid="wh-doorways">
            <button type="button" class="surface-secondary flex min-h-[44px] flex-col gap-1 p-5 text-left" data-testid="wh-knowledge" @click="go('knowledge')">
                <span class="font-mono text-[11px] tracking-widest text-text-muted">01</span>
                <span class="font-semibold">Knowledge</span>
                <p class="text-xs text-text-muted">Notes in this workspace</p>
                <KIcon name="arrow-up-right" :size="16" class="mt-auto self-end text-text-muted" />
            </button>
            <button type="button" class="surface-secondary flex min-h-[44px] flex-col gap-1 p-5 text-left" data-testid="wh-canvas" @click="go('canvas')">
                <span class="font-mono text-[11px] tracking-widest text-text-muted">02</span>
                <span class="font-semibold">Canvas</span>
                <p class="text-xs text-text-muted">Boards in this workspace</p>
                <KIcon name="arrow-up-right" :size="16" class="mt-auto self-end text-text-muted" />
            </button>
        </section>

        <!-- 6/7 · Upcoming → schedule; Progress → analytics -->
        <section class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-testid="wh-review">
            <button type="button" class="surface-secondary flex min-h-[44px] flex-col gap-1 p-5 text-left" data-testid="wh-upcoming" @click="go('schedule')">
                <span class="font-mono text-[11px] tracking-widest text-text-muted">03</span>
                <span class="font-semibold">Upcoming</span>
                <p class="text-xs text-text-muted">Draft and apply the week ahead</p>
                <KIcon name="arrow-up-right" :size="16" class="mt-auto self-end text-text-muted" />
            </button>
            <button type="button" class="surface-secondary flex min-h-[44px] flex-col gap-1 p-5 text-left" data-testid="wh-progress" @click="go('analytics')">
                <span class="font-mono text-[11px] tracking-widest text-text-muted">04</span>
                <span class="font-semibold">Progress</span>
                <p class="text-xs text-text-muted">What changed and why it matters</p>
                <KIcon name="arrow-up-right" :size="16" class="mt-auto self-end text-text-muted" />
            </button>
        </section>
    </div>
</template>
