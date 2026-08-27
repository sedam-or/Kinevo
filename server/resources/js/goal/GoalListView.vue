<script setup lang="ts">
import FeatureHelp from '../components/FeatureHelp.vue';
import { useGenerationStages } from '../components/useGenerationStages';
import { onMounted, reactive, ref } from 'vue';
import { useGoalStore } from './store';
import { useWorkspaceStore } from '../workspace/store';
import { GOAL_HORIZONS, PROGRAM_WORKLOAD_TYPES, type Goal } from './types';
import KButton from '../components/KButton.vue';
import KInput from '../components/KInput.vue';
import KIcon from '../components/KIcon.vue';
import ProposalReviewCard from '../ai/ProposalReviewCard.vue';
import AiNotConfiguredNotice from '../ai/AiNotConfiguredNotice.vue';
import { useAiSettingsStore } from '../ai/store';

const emit = defineEmits<{
    (e: 'selectGoal', goalId: number): void;
}>();

const goals = useGoalStore();
const ai = useAiSettingsStore();
const aiGateShown = ref(false);

const goalForm = reactive({
    title: '',
    description: '',
    horizon: 'yearly',
    targetDate: '',
    priorityTier: 3,
});

const programForm = reactive({
    name: '',
    workloadType: 'structured',
    weeklyTargetMinutes: null as number | null,
});

const formError = ref<string | null>(null);

/** The just-created goal waiting on a breakdown decision (P17-003). */
const suggestionGoal = ref<Goal | null>(null);
const { running: generating, label: generationStage, start: startGeneration, stop: stopGeneration } = useGenerationStages();
const suggestionError = ref<string | null>(null);
const proposalReady = ref(false);
/**
 * Inline review after generation (TASK-P17-026): the proposal is reviewed,
 * edited and accepted right inside the post-create panel — no navigation to
 * the goal detail page. Wiring mirrors GoalDetailView (TASK-P17-005).
 */
const hasPendingProposal = ref(false);
const breakdownAccepted = ref(false);
const reviewCard = ref<InstanceType<typeof ProposalReviewCard> | null>(null);

onMounted(() => {
    void goals.loadAll();
});

function horizonLabel(h: string): string {
    return h.charAt(0).toUpperCase() + h.slice(1);
}

function statusLabel(s: string): string {
    return s.replace(/_/g, ' ');
}

async function createGoal(): Promise<void> {
    formError.value = null;
    if (goalForm.title.trim() === '') {
        return;
    }
    const goal = await goals.createGoal({
        title: goalForm.title.trim(),
        description: goalForm.description.trim() === '' ? null : goalForm.description.trim(),
        horizon: goalForm.horizon,
        target_date: goalForm.targetDate === '' ? null : goalForm.targetDate,
        workspace_id: useWorkspaceStore().activeWorkspaceId ?? undefined,
        priority_tier: goalForm.priorityTier,
    });
    if (goal === null) {
        formError.value = goals.error?.message ?? 'Could not create goal.';
        return;
    }
    goalForm.title = '';
    goalForm.description = '';
    goalForm.targetDate = '';
    // Planning workflow: immediately offer to break the goal down. The goal
    // itself is never mutated automatically (design.md §104 AI safety rule).
    suggestionGoal.value = goal;
    proposalReady.value = false;
    suggestionError.value = null;
}

async function generateBreakdown(): Promise<void> {
    if (suggestionGoal.value === null) {
        return;
    }
    // TASK-P17-028: never fire a doomed request — route to Settings instead.
    await ai.ensureStatus();
    aiGateShown.value = !ai.generationReady;
    if (aiGateShown.value) {
        return;
    }
    startGeneration();
    suggestionError.value = null;
    const proposal = await goals.createBreakdownProposal(suggestionGoal.value.id);
    stopGeneration();
    if (proposal === null) {
        suggestionError.value = goals.error?.message ?? 'Could not generate a breakdown.';
        return;
    }
    // The proposal is persisted server-side as pending — no milestones were
    // created yet. Full review/edit/accept happens INLINE below (P17-026).
    proposalReady.value = true;
    breakdownAccepted.value = false;
    await reviewCard.value?.load();
}

/**
 * Inline accept (TASK-P17-026): the goal keeps milestones from the accepted
 * proposal, so the list is refreshed to reflect the new state.
 */
async function onBreakdownAccepted(): Promise<void> {
    breakdownAccepted.value = true;
    hasPendingProposal.value = false;
    await goals.loadAll();
}

function doItMyself(): void {
    if (suggestionGoal.value !== null) {
        emit('selectGoal', suggestionGoal.value.id);
    }
    suggestionGoal.value = null;
}

function dismissSuggestion(): void {
    suggestionGoal.value = null;
}

async function createProgram(): Promise<void> {
    formError.value = null;
    if (programForm.name.trim() === '') {
        return;
    }
    const program = await goals.createProgram({
        name: programForm.name.trim(),
        workload_type: programForm.workloadType,
        weekly_target_minutes: programForm.weeklyTargetMinutes,
    });
    if (program === null) {
        formError.value = goals.error?.message ?? 'Could not create program.';
        return;
    }
    programForm.name = '';
    programForm.weeklyTargetMinutes = null;
}

function workloadLabel(w: string): string {
    return w.charAt(0).toUpperCase() + w.slice(1);
}
</script>

<template>
    <div class="flex flex-col gap-8" data-testid="goals-view">
        <h1 class="text-xl font-semibold">Goals / Roadmap</h1>

        <div v-if="goals.loading" class="text-sm text-text-muted" data-testid="goals-loading">Loading…</div>
        <div v-if="goals.error" class="text-sm text-danger" role="alert" data-testid="goals-error">{{ goals.error.message }}</div>

        <!-- Create goal (planning workflow P17-003: outcome/deadline/description).
             Staged-primary rule (P17-010): this panel's CTA is primary until the
             post-create suggestion opens, then demotes to secondary. -->
        <section class="surface-primary p-5" data-testid="goal-create">
            <div class="text-xs uppercase tracking-wide text-text-muted mb-3">New goal</div>
            <form class="flex flex-wrap gap-3 items-end" @submit.prevent="createGoal">
                <div v-if="formError" class="w-full text-sm text-danger">{{ formError }}</div>
                <label class="flex flex-col gap-1 text-sm flex-1 min-w-40">
                    Outcome
                    <KInput v-model="goalForm.title" required placeholder="What do you want to achieve?" data-testid="goal-create-title" />
                </label>
                <label class="flex flex-col gap-1 text-sm flex-1 min-w-40">
                    Description
                    <textarea v-model="goalForm.description" rows="2" class="border border-border rounded-sm px-3 py-2 text-sm bg-bg text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus" data-testid="goal-create-description"></textarea>
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Horizon
                    <select v-model="goalForm.horizon" class="border border-border rounded-sm px-3 py-2 text-sm bg-bg text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus" data-testid="goal-create-horizon">
                        <option v-for="h in GOAL_HORIZONS" :key="h" :value="h">{{ horizonLabel(h) }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Deadline
                    <KInput v-model="goalForm.targetDate" type="date" data-testid="goal-create-date" />
                </label>
                <KButton type="submit" :variant="suggestionGoal ? 'secondary' : 'primary'" data-testid="goal-create-submit">Create</KButton>
            </form>
        </section>

        <!-- Post-creation planning suggestion (P17-003). The goal is never
             mutated automatically — the user chooses how to proceed. This is
             the decisive moment of the page (design.md §39): it carries the
             single surface-hero while open. -->
        <section
            v-if="suggestionGoal"
            class="surface-hero p-5"
            data-testid="goal-breakdown-suggestion"
        >
            <div class="text-xs uppercase tracking-wide text-text-muted mb-1">Goal created</div>
            <p class="text-sm text-text-muted mb-3 max-w-prose">
                “{{ suggestionGoal.title }}” is saved. Would you like Kinevo to break it down
                into actionable milestones?
            </p>
            <div v-if="proposalReady && !breakdownAccepted && hasPendingProposal" class="text-sm text-primary mb-3" data-testid="goal-proposal-ready" role="status">
                AI proposal generated — review it right here.
            </div>
            <div v-if="suggestionError" class="text-sm text-danger mb-3" role="alert" data-testid="goal-proposal-error">
                {{ suggestionError }}
            </div>
            <!-- TASK-P17-028: unconfigured AI routes to Settings, not an error. -->
            <AiNotConfiguredNotice v-if="aiGateShown && !hasPendingProposal" class="mb-3" />
            <div v-if="breakdownAccepted" class="text-sm text-text-muted mb-3" data-testid="goal-proposal-accepted" role="status">
                Breakdown accepted — milestones were added to “{{ suggestionGoal.title }}”.
            </div>
            <!-- Inline review (TASK-P17-026): nothing is accepted without review,
                 and reviewing does not leave the page. -->
            <ProposalReviewCard
                v-if="proposalReady && !breakdownAccepted"
                ref="reviewCard"
                :goal-id="suggestionGoal.id"
                @accepted="onBreakdownAccepted"
                @pending="hasPendingProposal = $event"
            />
            <div v-if="!hasPendingProposal && !breakdownAccepted" class="flex gap-2 flex-wrap mt-3">
                <KButton variant="primary" data-testid="goal-breakdown-ai" :disabled="generating" @click="generateBreakdown">
                    {{ generating ? generationStage : 'Generate with AI' }}
                </KButton>
                <KButton data-testid="goal-breakdown-manual" @click="doItMyself">I'll do it myself</KButton>
                <KButton variant="ghost" data-testid="goal-breakdown-later" @click="dismissSuggestion">Later</KButton>
            </div>
            <div v-else-if="breakdownAccepted" class="flex gap-2 flex-wrap mt-3">
                <KButton data-testid="goal-breakdown-open" @click="doItMyself">Open goal</KButton>
                <KButton variant="ghost" data-testid="goal-breakdown-close" @click="dismissSuggestion">Close</KButton>
            </div>
        </section>

        <!-- Goal list -->
        <section data-testid="goal-list">
            <div class="text-xs uppercase tracking-wide text-text-muted mb-2">Goals</div>
            <div
                v-if="goals.goals.length === 0 && !goals.loading"
                class="border-2 border-dashed border-border/40 rounded-sm p-6 flex flex-col items-center gap-2 text-center text-sm text-text-muted"
                data-testid="goal-empty"
            >
                <span>No goals yet.</span>
                <FeatureHelp
                    id="goal-roadmap"
                    variant="block"
                    title="Goals are the start of the roadmap"
                    body="A goal captures where you're heading. Kinevo can break it into milestones, programs, and tasks — you approve every step before anything is scheduled."
                />
            </div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                <article
                    v-for="goal in goals.goals"
                    :key="goal.id"
                    class="surface-secondary p-4 flex flex-col gap-2"
                    data-testid="goal-item"
                >
                    <div class="flex items-start justify-between gap-2">
                        <button
                            type="button"
                            class="group inline-flex items-center gap-1 font-semibold text-left rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                            data-testid="goal-open"
                            @click="emit('selectGoal', goal.id)"
                        >
                            {{ goal.title }}
                            <KIcon
                                name="arrow-up-right"
                                :size="14"
                                class="opacity-40 transition-opacity group-hover:opacity-100"
                            />
                        </button>
                        <span class="shrink-0 rounded-sm bg-surface border border-border px-2 py-0.5 text-xs">{{ statusLabel(goal.status) }}</span>
                    </div>
                    <!-- One dominant progress visualization per goal card (design.md §17) -->
                    <div data-testid="goal-progress-bar" role="img" :aria-label="`${goal.progress}% complete`">
                        <div class="h-2 rounded-sm bg-surface overflow-hidden">
                            <div
                                class="h-full bg-primary transition-all"
                                :style="{ width: `${Math.min(100, goal.progress)}%` }"
                            ></div>
                        </div>
                    </div>
                    <!-- Horizon / deadline: restrained metadata chips -->
                    <div class="flex flex-wrap gap-x-2 gap-y-1 font-mono text-[11px] text-text-muted">
                        <span data-testid="goal-progress-value">{{ goal.progress }}%</span>
                        <span class="rounded-sm bg-surface border border-border px-1.5 py-0.5">{{ horizonLabel(goal.horizon) }}</span>
                        <span v-if="goal.target_date" class="rounded-sm bg-surface border border-border px-1.5 py-0.5">due {{ goal.target_date }}</span>
                    </div>
                </article>
            </div>
        </section>

        <!-- Create program -->
        <section class="surface-secondary p-5" data-testid="program-create">
            <div class="text-xs uppercase tracking-wide text-text-muted mb-3">New program</div>
            <form class="flex flex-wrap gap-3 items-end" @submit.prevent="createProgram">
                <label class="flex flex-col gap-1 text-sm flex-1 min-w-40">
                    Name
                    <KInput v-model="programForm.name" type="text" required data-testid="program-create-name" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Workload
                    <select v-model="programForm.workloadType" class="border border-border rounded-sm px-3 py-2 text-sm bg-bg text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus" data-testid="program-create-workload">
                        <option v-for="w in PROGRAM_WORKLOAD_TYPES" :key="w" :value="w">{{ workloadLabel(w) }}</option>
                    </select>
                </label>
                <KButton type="submit" variant="secondary" data-testid="program-create-submit">Add</KButton>
            </form>
        </section>

        <!-- Program list -->
        <section data-testid="program-list">
            <div class="text-xs uppercase tracking-wide text-text-muted mb-2">Programs</div>
            <div v-if="goals.programs.length === 0 && !goals.loading" class="border-2 border-dashed border-border/40 rounded-sm p-6 text-center text-sm text-text-muted">No programs yet.</div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                <article v-for="program in goals.programs" :key="program.id" class="surface-secondary p-4 flex flex-col gap-2" data-testid="program-item">
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-semibold">{{ program.name }}</span>
                        <span class="shrink-0 rounded-sm bg-surface border border-border px-2 py-0.5 text-xs">{{ statusLabel(program.status) }}</span>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-1 font-mono text-[11px] text-text-muted">
                        <span>{{ workloadLabel(program.workload_type) }}</span>
                        <span v-if="program.weekly_target_minutes">· {{ program.weekly_target_minutes }}m/wk</span>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>
