<script setup lang="ts">
import { onMounted, reactive, watch } from 'vue';
import { useGoalStore } from './store';
import { MILESTONE_STATUSES, type Goal } from './types';
import type { EntityLink } from '../components/EntityLinks.vue';
import EntityLinks from '../components/EntityLinks.vue';

const props = defineProps<{
    goalId: number;
}>();

const emit = defineEmits<{
    (e: 'back'): void;
}>();

const goals = useGoalStore();

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
            <span class="text-xs rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-0.5" data-testid="goal-detail-status">
                {{ goals.currentGoal ? statusLabel(goals.currentGoal.status) : '' }}
            </span>
        </header>

        <div v-if="goals.loading" class="text-sm text-gray-500" data-testid="goal-detail-loading">Loading…</div>
        <div v-if="goals.error" class="text-sm text-danger" role="alert" data-testid="goal-detail-error">{{ goals.error.message }}</div>

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

            <!-- Milestones timeline -->
            <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="goal-milestones">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Milestones</div>
                <form class="flex gap-2 mb-3" @submit.prevent="addMilestone">
                    <input v-model="milestoneForm.title" type="text" placeholder="Add milestone" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm flex-1" data-testid="milestone-title" />
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
