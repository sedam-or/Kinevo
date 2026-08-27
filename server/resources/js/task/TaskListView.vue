<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import FeatureHelp from '../components/FeatureHelp.vue';
import { useTaskStore } from './store';
import { TASK_TRANSITIONS, type Task, type TaskStatusValue } from './types';
import VisualStateBadge from '../visualstate/VisualStateBadge.vue';
import { taskStates } from '../visualstate/derive';
import KButton from '../components/KButton.vue';
import KInput from '../components/KInput.vue';
import KIcon from '../components/KIcon.vue';
import type { VisualStateValue } from '../visualstate/types';

const emit = defineEmits<{
    (e: 'select', taskId: number): void;
}>();

const tasks = useTaskStore();

function todayDate(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function taskBadges(task: Task): VisualStateValue[] {
    return taskStates({ status: task.status, dueAt: task.due_at }, todayDate());
}

const createForm = reactive({
    title: '',
    priorityTier: 3,
    estimatedMinutes: null as number | null,
    dueAt: '',
});

const createError = ref<string | null>(null);

onMounted(() => {
    void tasks.loadList();
});

async function createTask(): Promise<void> {
    createError.value = null;
    if (createForm.title.trim() === '') {
        return;
    }
    const task = await tasks.create({
        title: createForm.title.trim(),
        priority_tier: createForm.priorityTier,
        estimated_minutes: createForm.estimatedMinutes,
        due_at: createForm.dueAt === '' ? null : createForm.dueAt,
    });
    if (task === null) {
        createError.value = tasks.error?.message ?? 'Could not create task.';
        return;
    }
    createForm.title = '';
    createForm.dueAt = '';
    createForm.estimatedMinutes = null;
}

function transitionsFor(task: Task): TaskStatusValue[] {
    return TASK_TRANSITIONS[task.status as TaskStatusValue] ?? [];
}

function statusLabel(status: string): string {
    return status.replace(/_/g, ' ');
}

async function applyStatus(task: Task, status: TaskStatusValue): Promise<void> {
    await tasks.setStatus(task.id, status);
}
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="task-view">
        <header class="flex flex-col gap-1">
            <div class="font-mono text-xs uppercase tracking-widest text-text-muted">Tasks</div>
            <h1 class="text-xl font-semibold">Tasks</h1>
        </header>

        <!-- Create (§P17-010: the page's ONE primary action) -->
        <section class="surface-secondary p-4" data-testid="task-create">
            <div class="text-xs uppercase text-text-muted mb-2">New task</div>
            <form class="flex flex-wrap gap-3 items-end" @submit.prevent="createTask">
                <div v-if="createError" class="w-full text-sm text-danger" role="alert">{{ createError }}</div>
                <label class="flex flex-col gap-1 text-sm flex-1 min-w-40">
                    Title
                    <KInput v-model="createForm.title" required class="flex-1 min-w-40" data-testid="task-create-title" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Priority
                    <select v-model.number="createForm.priorityTier" class="border border-border rounded-sm px-3 py-2 bg-bg text-text" data-testid="task-create-priority">
                        <option :value="1">High</option>
                        <option :value="2">Medium</option>
                        <option :value="3">Low</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Due
                    <KInput v-model="createForm.dueAt" type="date" data-testid="task-create-due" />
                </label>
                <KButton type="submit" variant="primary" data-testid="task-create-submit">
                    <KIcon name="plus" :size="16" />
                    Add
                </KButton>
            </form>
        </section>

        <div v-if="tasks.loading" class="text-sm text-text-muted" data-testid="task-loading">Loading tasks…</div>
        <div v-if="tasks.error" class="text-sm text-danger" role="alert" data-testid="task-error">{{ tasks.error.message }}</div>

        <!-- List -->
        <section data-testid="task-list">
            <div v-if="tasks.tasks.length === 0 && !tasks.loading" class="text-sm text-text-muted border border-dashed border-border/30 rounded-sm p-4 flex flex-col gap-2" data-testid="task-empty">
                <span>No tasks yet.</span>
                <FeatureHelp
                    id="tasks-feed-schedule"
                    variant="block"
                    title="Tasks feed the schedule"
                    body="Tasks hold the work; the scheduler finds the time. Once a task has a duration and priority, drafting a schedule places it on your timeline."
                />
            </div>
            <article
                v-for="task in tasks.tasks"
                :key="task.id"
                class="surface-metadata border-b border-border/20 py-4 last:border-b-0"
                data-testid="task-item"
            >
                <div class="flex items-center justify-between gap-2">
                    <button
                        type="button"
                        class="font-semibold text-left hover:underline underline-offset-2 rounded-sm"
                        data-testid="task-open"
                        @click="emit('select', task.id)"
                    >
                        {{ task.title }}
                    </button>
                    <div class="flex items-center gap-2 shrink-0">
                        <VisualStateBadge v-for="s in taskBadges(task)" :key="s" :state="s" />
                        <span class="text-xs rounded-sm bg-surface border border-border text-text px-2 py-0.5" data-testid="task-status">
                            {{ statusLabel(task.status) }}
                        </span>
                    </div>
                </div>
                <div class="text-xs text-text-muted mt-1">
                    {{ task.priority_tier === 1 ? 'High' : task.priority_tier === 2 ? 'Medium' : 'Low' }}
                    <span v-if="task.estimated_minutes"> · {{ task.estimated_minutes }}m</span>
                    <span v-if="task.due_at"> · due {{ task.due_at.slice(0, 10) }}</span>
                    <span v-if="task.progress > 0"> · {{ task.progress }}%</span>
                </div>

                <!-- Status transitions (present valid actions only; backend is authority) -->
                <div v-if="transitionsFor(task).length > 0" class="flex flex-wrap gap-2 mt-3">
                    <KButton
                        v-for="next in transitionsFor(task)"
                        :key="next"
                        variant="ghost"
                        :data-testid="`task-to-${next}`"
                        @click="applyStatus(task, next)"
                    >
                        {{ statusLabel(next) }}
                    </KButton>
                </div>
            </article>
        </section>
    </div>
</template>
