<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useGoalStore } from './store';
import { MILESTONE_STATUSES, type Goal } from './types';
import type { EntityLink } from '../components/EntityLinks.vue';
import EntityLinks from '../components/EntityLinks.vue';
import ProposalReviewCard from '../ai/ProposalReviewCard.vue';
import AiNotConfiguredNotice from '../ai/AiNotConfiguredNotice.vue';
import { useAiSettingsStore } from '../ai/store';
import { useGenerationStages } from '../components/useGenerationStages';
import NextActionBanner from '../components/NextActionBanner.vue';
import { resolveGoalNextAction, type NextAction } from '../next-action';
import { useShellStore } from '../shell/store';

const props = defineProps<{
    goalId: number;
}>();

const emit = defineEmits<{
    (e: 'back'): void;
}>();

const goals = useGoalStore();

/**
 * Post-goal AI invocation (TASK-P17-005): breakdown is reachable where the
 * goal lives — header action and the empty-milestone state — without visiting
 * Settings or another AI page. Generation goes through the same validated
 * proposal contract as everywhere else (FR-52/FR-62).
 */
const reviewCard = ref<InstanceType<typeof ProposalReviewCard> | null>(null);
const milestoneTitleInput = ref<HTMLInputElement | null>(null);
const shell = useShellStore();
const ai = useAiSettingsStore();
const hasPendingProposal = ref(false);
const aiGateShown = ref(false);
const { running: generating, label: generationStage, start: startGeneration, stop: stopGeneration } = useGenerationStages();
const generateError = ref<string | null>(null);

async function generateBreakdown(): Promise<void> {
    if (generating.value || hasPendingProposal.value) {
        return;
    }
    // TASK-P17-028: unconfigured AI routes to Settings instead of a 503.
    await ai.ensureStatus();
    aiGateShown.value = !ai.generationReady;
    if (aiGateShown.value) {
        return;
    }
    startGeneration();
    generateError.value = null;
    const proposal = await goals.createBreakdownProposal(props.goalId);
    if (proposal === null) {
        generateError.value = goals.error?.message ?? 'AI breakdown could not be generated.';
    } else {
        await reviewCard.value?.load();
    }
    stopGeneration();
}

const milestoneCount = computed(() => sortedMilestones().length);

// Next Action Engine (TASK-P17-016): one suggested action per goal state.
const nextAction = computed(() =>
    resolveGoalNextAction({
        milestoneCount: milestoneCount.value,
        hasPendingProposal: hasPendingProposal.value,
        openMilestoneTitle:
            sortedMilestones().find((m) => m.status === 'planned' || m.status === 'active')?.title ?? null,
    }),
);

function onGoalNextAction(id: NextAction['id']): void {
    if (id === 'create-milestone') {
        milestoneTitleInput.value?.focus();
        return;
    }
    if (id === 'review-proposal') {
        (reviewCard.value?.$el as HTMLElement | undefined)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
    }
    shell.setView('today');
}

const milestoneForm = reactive({
    title: '',
    targetDate: '',
});

onMounted(() => {
    void goals.loadGoal(props.goalId);
});

watch(
    () => props.goalId,
    (id) => void goals.loadGoal(id),
);

/**
 * Workflow continuity (TASK-P17-002): downstream execution surfaces for this
 * goal — its tasks, the schedule that places them, and the progress/analytics
 * that reflect movement. Milestones are inline above; programs surface through
 * tasks.
 */
const downstreamLinks: EntityLink[] = [
    { label: 'Tasks', view: 'tasks' },
    { label: 'Schedule', view: 'schedule' },
    { label: 'Progress', view: 'analytics' },
];

function statusLabel(s: string): string {
    return s.replace(/_/g, ' ');
}

async function addMilestone(): Promise<void> {
    if (milestoneForm.title.trim() === '') {
        return;
    }
    await goals.createMilestone(props.goalId, {
        title: milestoneForm.title.trim(),
        target_date: milestoneForm.targetDate === '' ? null : milestoneForm.targetDate,
    });
    milestoneForm.title = '';
    milestoneForm.targetDate = '';
}

async function applyMilestoneStatus(milestoneId: number, status: string): Promise<void> {
    await goals.setMilestoneStatus(props.goalId, milestoneId, status);
}

async function applyGoalStatus(status: string): Promise<void> {
    await goals.setGoalStatus(props.goalId, status);
}

function goalStatusActions(goal: Goal | null): string[] {
    if (!goal) {
        return [];
    }
    const actions: Record<string, string[]> = {
        draft: ['active'],
        active: ['paused', 'completed', 'archived', 'dropped'],
        paused: ['active', 'completed', 'dropped'],
    };
    return actions[goal.status] ?? [];
}

function sortedMilestones() {
    return [...goals.milestones].sort((a, b) => a.sequence - b.sequence);
}

/** Roadmap glyph per milestone state (design.md §39: ✓ done, ● active, ○ planned). */
function milestoneGlyph(status: string): string {
    if (status === 'completed') {
        return '✓';
    }
    if (status === 'active') {
        return '●';
    }
    if (status === 'dropped' || status === 'blocked') {
        return '✕';
    }
    return '○';
}

function milestoneGlyphEmphasis(status: string): string {
    if (status === 'completed') {
        return 'text-[var(--color-success)]';
    }
    if (status === 'active') {
        return 'text-[var(--color-info)]';
    }
    if (status === 'dropped' || status === 'blocked') {
        return 'text-[var(--color-danger)]';
    }
    return 'text-text-muted';
}
</script>

<template>
    <div class="flex flex-col gap-6" data-testid="goal-detail">
        <header class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button type="button" class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="goal-detail-back" @click="emit('back')">← Back</button>
                <h1 class="text-xl font-semibold" data-testid="goal-detail-title">{{ goals.currentGoal?.title ?? 'Goal' }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-0.5" data-testid="goal-detail-status">
                    {{ goals.currentGoal ? statusLabel(goals.currentGoal.status) : '' }}
                </span>
                <button
                    v-if="!hasPendingProposal"
                    type="button"
                    class="text-sm border border-[var(--color-primary)] text-[var(--color-primary)] rounded-sm px-3 py-1 disabled:opacity-50"
                    data-testid="goal-detail-breakdown"
                    :disabled="generating"
                    @click="generateBreakdown"
                >{{ generating ? generationStage : 'Break Down with AI' }}</button>
            </div>
        </header>
        <NextActionBanner
            v-if="nextAction"
            :action="nextAction"
            @act="onGoalNextAction"
        />

        <div v-if="goals.loading" class="text-sm text-gray-500" data-testid="goal-detail-loading">Loading…</div>
        <div v-if="goals.error" class="text-sm text-danger" role="alert" data-testid="goal-detail-error">{{ goals.error.message }}</div>
        <div v-if="generateError" class="text-sm text-danger" role="alert" data-testid="goal-detail-generate-error">{{ generateError }}</div>
        <!-- TASK-P17-028: unconfigured AI routes to Settings, not an error. -->
        <AiNotConfiguredNotice v-if="aiGateShown && !hasPendingProposal" class="mb-2" />

        <EntityLinks :links="downstreamLinks" />

        <template v-if="goals.currentGoal">
            <!-- Outcome -->
            <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="goal-outcome">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Outcome</div>
                <p class="text-sm">{{ goals.currentGoal.description || 'No description.' }}</p>
            </section>

            <!-- Deadline / Progress -->
            <section data-testid="goal-meta">
                <div class="flex flex-wrap gap-2 text-sm">
                    <span class="rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-1">Horizon: {{ goals.currentGoal.horizon }}</span>
                    <span v-if="goals.currentGoal.target_date" class="rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-1">Deadline: {{ goals.currentGoal.target_date }}</span>
                </div>
                <!-- One dominant progress visualization (design.md §17, §39:
                     milestone roadmap preferred over multiple rings). -->
                <div class="mt-2" data-testid="goal-progress-bar" role="img" :aria-label="`${goals.currentGoal.progress}% complete`">
                    <div class="h-2 rounded-sm bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div
                            class="h-full bg-[var(--color-primary)] transition-all"
                            :style="{ width: `${Math.min(100, goals.currentGoal.progress)}%` }"
                        ></div>
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                        <span data-testid="goal-progress">Progress: {{ goals.currentGoal.progress }}%</span>
                    </div>
                </div>
            </section>

            <!-- Status actions -->
            <section v-if="goalStatusActions(goals.currentGoal).length > 0" class="flex gap-2 flex-wrap" data-testid="goal-actions">
                <button
                    v-for="status in goalStatusActions(goals.currentGoal)"
                    :key="status"
                    type="button"
                    class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1"
                    :data-testid="`goal-to-${status}`"
                    @click="applyGoalStatus(status)"
                >
                    {{ statusLabel(status) }}
                </button>
            </section>

            <!-- Pending AI breakdown proposal: review → edit → accept/reject (TASK-P17-004) -->
            <ProposalReviewCard ref="reviewCard" :goal-id="goalId" @accepted="goals.loadGoal(goalId)" @pending="hasPendingProposal = $event" />

            <!-- Milestones timeline -->
            <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="goal-milestones">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Milestones</div>
                <!-- Empty-milestone state: explicit AI entry point (TASK-P17-005). -->
                <div v-if="milestoneCount === 0 && !hasPendingProposal" class="mb-3 flex items-center justify-between gap-3 rounded-sm bg-gray-50 dark:bg-gray-800 px-3 py-2" data-testid="milestones-empty">
                    <span class="text-sm text-gray-600 dark:text-gray-400">No milestones yet.</span>
                    <button
                        type="button"
                        class="text-sm border border-[var(--color-primary)] text-[var(--color-primary)] rounded-sm px-3 py-1 disabled:opacity-50"
                        data-testid="milestones-empty-breakdown"
                        :disabled="generating"
                        @click="generateBreakdown"
                    >{{ generating ? generationStage : 'Break Down with AI' }}</button>
                </div>
                <form class="flex gap-2 mb-3" @submit.prevent="addMilestone">
                    <input ref="milestoneTitleInput" v-model="milestoneForm.title" type="text" placeholder="Add milestone" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm flex-1" data-testid="milestone-title" />
                    <input v-model="milestoneForm.targetDate" type="date" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm" data-testid="milestone-date" />
                    <button type="submit" class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1" data-testid="milestone-add">Add</button>
                </form>

                <ol class="relative border-l border-gray-200 dark:border-gray-700 ml-2 space-y-4" data-testid="milestone-timeline">
                    <li v-for="ms in sortedMilestones()" :key="ms.id" class="ml-4" data-testid="milestone-item">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span
                                    class="text-sm"
                                    :class="milestoneGlyphEmphasis(ms.status)"
                                    :aria-label="`${statusLabel(ms.status)}`"
                                >{{ milestoneGlyph(ms.status) }}</span>
                                <span class="font-medium text-sm">{{ ms.title }}</span>
                            </span>
                            <span class="text-xs rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-0.5">{{ statusLabel(ms.status) }}</span>
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                            <span v-if="ms.target_date">due {{ ms.target_date }}</span>
                            <span v-if="ms.estimated_minutes"> · {{ ms.estimated_minutes }}m</span>
                            <span v-if="ms.progress > 0"> · {{ ms.progress }}%</span>
                        </div>
                        <div class="flex gap-2 mt-1 flex-wrap">
                            <button
                                v-for="next in MILESTONE_STATUSES"
                                :key="next"
                                type="button"
                                class="text-xs border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-0.5"
                                :data-testid="`milestone-to-${next}`"
                                @click="applyMilestoneStatus(ms.id, next)"
                            >
                                {{ statusLabel(next) }}
                            </button>
                        </div>
                    </li>
                    <li v-if="goals.milestones.length === 0" class="ml-4 text-sm text-gray-500 dark:text-gray-400">No milestones yet.</li>
                </ol>
            </section>
        </template>
    </div>
</template>
