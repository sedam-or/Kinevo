<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useGoalStore } from './store';
import { GOAL_HORIZONS, PROGRAM_WORKLOAD_TYPES } from './types';
import KButton from '../components/KButton.vue';
import KInput from '../components/KInput.vue';

const emit = defineEmits<{
    (e: 'selectGoal', goalId: number): void;
}>();

const goals = useGoalStore();

const goalForm = reactive({
    title: '',
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
        horizon: goalForm.horizon,
        target_date: goalForm.targetDate === '' ? null : goalForm.targetDate,
        priority_tier: goalForm.priorityTier,
    });
    if (goal === null) {
        formError.value = goals.error?.message ?? 'Could not create goal.';
        return;
    }
    goalForm.title = '';
    goalForm.targetDate = '';
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
        <div v-if="goals.error" class="text-sm text-[#F53003]" role="alert" data-testid="goals-error">{{ goals.error.message }}</div>

        <!-- Create goal -->
        <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="goal-create">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">New goal</div>
            <form class="flex flex-wrap gap-3 items-end" @submit.prevent="createGoal">
                <div v-if="formError" class="w-full text-sm text-[#F53003]">{{ formError }}</div>
                <label class="flex flex-col gap-1 text-sm flex-1 min-w-40">
                    Title
                    <KInput v-model="goalForm.title" required class="flex-1 min-w-40" data-testid="goal-create-title" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Horizon
                    <select v-model="goalForm.horizon" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="goal-create-horizon">
                        <option v-for="h in GOAL_HORIZONS" :key="h" :value="h">{{ horizonLabel(h) }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Target date
                    <input v-model="goalForm.targetDate" type="date" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="goal-create-date" />
                </label>
                <KButton type="submit" variant="primary" data-testid="goal-create-submit">Add</KButton>
            </form>
        </section>

        <!-- Goal list -->
        <section data-testid="goal-list">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Goals</div>
            <div v-if="goals.goals.length === 0 && !goals.loading" class="text-sm text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-600 rounded-sm p-4">No goals yet. Create a goal above to begin your roadmap.</div>
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
                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                    {{ horizonLabel(goal.horizon) }}
                    <span v-if="goal.target_date"> · due {{ goal.target_date }}</span>
                    <span v-if="goal.progress > 0"> · {{ goal.progress }}%</span>
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
