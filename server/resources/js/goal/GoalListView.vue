<script setup lang="ts">
import FeatureHelp from '../components/FeatureHelp.vue';
import { onMounted, reactive, ref } from 'vue';
import { useGoalStore } from './store';
import { GOAL_HORIZONS, PROGRAM_WORKLOAD_TYPES, type Goal } from './types';
import KButton from '../components/KButton.vue';
import KInput from '../components/KInput.vue';

const emit = defineEmits<{
    (e: 'selectGoal', goalId: number): void;
}>();

const goals = useGoalStore();

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
const generating = ref(false);
const suggestionError = ref<string | null>(null);
const proposalReady = ref(false);

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
    generating.value = true;
    suggestionError.value = null;
    const proposal = await goals.createBreakdownProposal(suggestionGoal.value.id);
    generating.value = false;
    if (proposal === null) {
        suggestionError.value = goals.error?.message ?? 'Could not generate a breakdown.';
        return;
    }
    // The proposal is persisted server-side as pending — no milestones were
    // created yet. Full review/edit/accept UX lands in TASK-P17-004.
    proposalReady.value = true;
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
    <div class="flex flex-col gap-6" data-testid="goals-view">
        <h1 class="text-xl font-semibold">Goals / Roadmap</h1>

        <div v-if="goals.loading" class="text-sm text-gray-500" data-testid="goals-loading">Loading…</div>
        <div v-if="goals.error" class="text-sm text-danger" role="alert" data-testid="goals-error">{{ goals.error.message }}</div>

        <!-- Create goal (planning workflow P17-003: outcome/deadline/description) -->
        <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="goal-create">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">New goal</div>
            <form class="flex flex-wrap gap-3 items-end" @submit.prevent="createGoal">
                <div v-if="formError" class="w-full text-sm text-danger">{{ formError }}</div>
                <label class="flex flex-col gap-1 text-sm flex-1 min-w-40">
                    Outcome
                    <KInput v-model="goalForm.title" required placeholder="What do you want to achieve?" class="flex-1 min-w-40" data-testid="goal-create-title" />
                </label>
                <label class="flex flex-col gap-1 text-sm flex-1 min-w-40">
                    Description
                    <textarea v-model="goalForm.description" rows="2" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2 text-sm flex-1 min-w-40" data-testid="goal-create-description"></textarea>
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Horizon
                    <select v-model="goalForm.horizon" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="goal-create-horizon">
                        <option v-for="h in GOAL_HORIZONS" :key="h" :value="h">{{ horizonLabel(h) }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Deadline
                    <input v-model="goalForm.targetDate" type="date" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="goal-create-date" />
                </label>
                <KButton type="submit" variant="primary" data-testid="goal-create-submit">Create</KButton>
            </form>
        </section>

        <!-- Post-creation planning suggestion (P17-003). The goal is never
             mutated automatically — the user chooses how to proceed. -->
        <section
            v-if="suggestionGoal"
            class="border-2 border-[var(--color-primary)] dark:border-[var(--color-primary)] rounded-sm p-4"
            data-testid="goal-breakdown-suggestion"
        >
            <div class="font-medium mb-1">Goal created</div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                “{{ suggestionGoal.title }}” is saved. Would you like Kinevo to break it down
                into actionable milestones?
            </p>
            <div v-if="proposalReady" class="text-sm text-[var(--color-primary)] mb-3" data-testid="goal-proposal-ready" role="status">
                AI proposal generated. Open the goal to review, edit, and accept it.
            </div>
            <div v-if="suggestionError" class="text-sm text-danger mb-3" role="alert" data-testid="goal-proposal-error">
                {{ suggestionError }}
            </div>
            <div class="flex gap-2 flex-wrap">
                <KButton variant="primary" data-testid="goal-breakdown-ai" :disabled="generating" @click="generateBreakdown">
                    {{ generating ? 'Generating…' : 'Generate with AI' }}
                </KButton>
                <KButton data-testid="goal-breakdown-manual" @click="doItMyself">I'll do it myself</KButton>
                <KButton variant="ghost" data-testid="goal-breakdown-later" @click="dismissSuggestion">Later</KButton>
            </div>
        </section>

        <!-- Goal list -->
        <section data-testid="goal-list">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Goals</div>
            <div v-if="goals.goals.length === 0 && !goals.loading" class="text-sm text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-600 rounded-sm p-4 flex flex-col gap-2" data-testid="goal-empty">
                <span>No goals yet.</span>
                <FeatureHelp
                    id="goal-roadmap"
                    variant="block"
                    title="Goals are the start of the roadmap"
                    body="A goal captures where you're heading. Kinevo can break it into milestones, programs, and tasks — you approve every step before anything is scheduled."
                />
            </div>
            <article
                v-for="goal in goals.goals"
                :key="goal.id"
                class="border border-gray-300 dark:border-gray-600 rounded-sm p-3 mb-2"
                data-testid="goal-item"
            >
                <div class="flex items-center justify-between">
                    <button type="button" class="font-medium text-left" data-testid="goal-open" @click="emit('selectGoal', goal.id)">
                        {{ goal.title }}
                    </button>
                    <span class="text-xs rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-0.5">{{ statusLabel(goal.status) }}</span>
                </div>
                <!-- One dominant progress visualization per goal card (design.md §17) -->
                <div class="mt-2" data-testid="goal-progress-bar" role="img" :aria-label="`${goal.progress}% complete`">
                    <div class="h-2 rounded-sm bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div
                            class="h-full bg-primary transition-all"
                            :style="{ width: `${Math.min(100, goal.progress)}%` }"
                        ></div>
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                        <span data-testid="goal-progress-value">{{ goal.progress }}%</span>
                        <span v-if="goal.target_date"> · due {{ goal.target_date }}</span>
                    </div>
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                    {{ horizonLabel(goal.horizon) }}
                </div>
            </article>
        </section>

        <!-- Create program -->
        <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="program-create">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">New program</div>
            <form class="flex flex-wrap gap-3 items-end" @submit.prevent="createProgram">
                <label class="flex flex-col gap-1 text-sm flex-1 min-w-40">
                    Name
                    <input v-model="programForm.name" type="text" required class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="program-create-name" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Workload
                    <select v-model="programForm.workloadType" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="program-create-workload">
                        <option v-for="w in PROGRAM_WORKLOAD_TYPES" :key="w" :value="w">{{ workloadLabel(w) }}</option>
                    </select>
                </label>
                <KButton type="submit" variant="primary" data-testid="program-create-submit">Add</KButton>            </form>
        </section>

        <!-- Program list -->
        <section data-testid="program-list">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Programs</div>
            <div v-if="goals.programs.length === 0 && !goals.loading" class="text-sm text-gray-500 dark:text-gray-400">No programs yet.</div>
            <article v-for="program in goals.programs" :key="program.id" class="border border-gray-300 dark:border-gray-600 rounded-sm p-3 mb-2" data-testid="program-item">
                <div class="flex items-center justify-between">
                    <span class="font-medium">{{ program.name }}</span>
                    <span class="text-xs rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-0.5">{{ statusLabel(program.status) }}</span>
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                    {{ workloadLabel(program.workload_type) }}
                    <span v-if="program.weekly_target_minutes"> · {{ program.weekly_target_minutes }}m/wk</span>
                </div>
            </article>
        </section>
    </div>
</template>
