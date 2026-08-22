<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useTaskStore } from './store';
import { TASK_TRANSITIONS, type Task, type TaskStatusValue } from './types';
import type { EntityLink } from '../components/EntityLinks.vue';
import EntityLinks from '../components/EntityLinks.vue';
import AttachmentList from '../attachments/AttachmentList.vue';
import KButton from '../components/KButton.vue';
import KInput from '../components/KInput.vue';

const props = defineProps<{
    taskId: number;
}>();

const emit = defineEmits<{
    (e: 'back'): void;
}>();

const tasks = useTaskStore();

const editForm = reactive({
    title: '',
    description: '',
    priorityTier: 3,
    estimatedMinutes: null as number | null,
    dueAt: '',
});

const newSubtaskTitle = ref('');
const subtaskError = ref<string | null>(null);

function syncForm(task: Task): void {
    editForm.title = task.title;
    editForm.description = task.description ?? '';
    editForm.priorityTier = task.priority_tier;
    editForm.estimatedMinutes = task.estimated_minutes;
    editForm.dueAt = task.due_at?.slice(0, 10) ?? '';
}

onMounted(async () => {
    await tasks.load(props.taskId);
    if (tasks.current) {
        syncForm(tasks.current);
    }
});

watch(
    () => props.taskId,
    async (id) => {
        await tasks.load(id);
        if (tasks.current) {
            syncForm(tasks.current);
        }
    },
);

function statusLabel(status: string): string {
    return status.replace(/_/g, ' ');
}

/** The one recommended next action per task state (design.md §19). */
const PRIMARY_ACTION: Partial<Record<TaskStatusValue, TaskStatusValue>> = {
    backlog: 'scheduled',
    scheduled: 'in_progress',
    in_progress: 'completed',
    partial: 'continued',
    continued: 'in_progress',
    missed: 'scheduled',
    conflict: 'in_progress',
};

/** A more executable label for the recommended transition (design.md §19). */
const PRIMARY_LABEL: Partial<Record<TaskStatusValue, string>> = {
    scheduled: 'Schedule',
    in_progress: 'Start',
    completed: 'Complete',
    continued: 'Continue',
};

function transitionsFor(task: Task | null): TaskStatusValue[] {
    if (!task) {
        return [];
    }
    return TASK_TRANSITIONS[task.status as TaskStatusValue] ?? [];
}

function primaryActionFor(task: Task | null): TaskStatusValue | null {
    if (!task) {
        return null;
    }
    const status = task.status as TaskStatusValue;
    const preferred = PRIMARY_ACTION[status];
    if (preferred && transitionsFor(task).includes(preferred)) {
        return preferred;
    }
    // No firmly recommended action; fall back to the first valid transition.
    return transitionsFor(task)[0] ?? null;
}

function primaryLabelFor(status: TaskStatusValue | null): string {
    if (!status) {
        return '';
    }
    return PRIMARY_LABEL[status] ?? statusLabel(status);
}

function secondaryActionsFor(task: Task | null): TaskStatusValue[] {
    const primary = primaryActionFor(task);
    return transitionsFor(task).filter((s) => s !== primary);
}

async function saveEdit(): Promise<void> {
    if (!tasks.current) {
        return;
    }
    await tasks.apiUpdate(props.taskId, {
        title: editForm.title,
        description: editForm.description,
        priority_tier: editForm.priorityTier,
        estimated_minutes: editForm.estimatedMinutes,
        due_at: editForm.dueAt === '' ? null : editForm.dueAt,
    });
}

async function applyStatus(status: TaskStatusValue): Promise<void> {
    await tasks.setStatus(props.taskId, status);
}

async function addSubtask(): Promise<void> {
    subtaskError.value = null;
    if (newSubtaskTitle.value.trim() === '') {
        return;
    }
    await tasks.addSubtask(props.taskId, newSubtaskTitle.value.trim());
    newSubtaskTitle.value = '';
}

async function toggle(subtaskId: number): Promise<void> {
    await tasks.toggleSubtask(props.taskId, subtaskId);
}

async function partialComplete(): Promise<void> {
    await tasks.partialComplete(props.taskId);
}

async function promote(subtaskId: number): Promise<void> {
    await tasks.promoteSubtask(props.taskId, subtaskId);
}

/**
 * Workflow continuity (TASK-P17-002): upstream planning context and
 * downstream knowledge/execution surfaces, so a task page is never a
 * dead end. Milestone/program have no dedicated surfaces — their goal
 * carries them, so the Goal chip is the single upstream entry.
 */
const relatedLinks = computed<EntityLink[]>(() => {
    const task = tasks.current;
    const links: EntityLink[] = [];
    if (task?.goal_id) {
        links.push({ label: 'Goal', view: 'goals', focusId: task.goal_id });
    }
    links.push(
        { label: 'Schedule', view: 'schedule' },
        { label: 'Notes', view: 'knowledge' },
        { label: 'Canvas', view: 'canvas' },
    );
    return links;
});
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="task-detail">
        <header class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <KButton variant="ghost" data-testid="task-detail-back" @click="emit('back')">← Back</KButton>
                <h1 class="text-xl font-semibold" data-testid="task-detail-title">{{ tasks.current?.title ?? 'Task' }}</h1>
            </div>
            <span class="text-xs rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-0.5" data-testid="task-detail-status">
                {{ tasks.current ? statusLabel(tasks.current.status) : '' }}
            </span>
        </header>

        <div v-if="tasks.loading" class="text-sm text-gray-500" data-testid="task-detail-loading">Loading…</div>
        <div v-if="tasks.error" class="text-sm text-danger" role="alert" data-testid="task-detail-error">{{ tasks.error.message }}</div>

        <EntityLinks :links="relatedLinks" />

        <!-- Editform -->
        <section v-if="tasks.current" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="task-edit">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Edit</div>
            <form class="flex flex-col gap-3" @submit.prevent="saveEdit">
                <label class="flex flex-col gap-1 text-sm">
                    Title
                    <KInput v-model="editForm.title" required data-testid="task-edit-title" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Description
                    <textarea v-model="editForm.description" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="task-edit-description"></textarea>
                </label>
                <div class="flex gap-3">
                    <label class="flex flex-col gap-1 text-sm">
                        Priority
                        <select v-model.number="editForm.priorityTier" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="task-edit-priority">
                            <option :value="1">High</option>
                            <option :value="2">Medium</option>
                            <option :value="3">Low</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        Duration (min)
                        <input v-model.number="editForm.estimatedMinutes" type="number" min="1" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="task-edit-duration" />
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        Due
                        <input v-model="editForm.dueAt" type="date" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="task-edit-due" />
                    </label>
                </div>
                <KButton type="submit" variant="primary" class="self-start" data-testid="task-edit-save">
                    Save
                </KButton>
            </form>
        </section>

        <!-- Status transitions + partial completion (design.md §19: one primary action per state) -->
        <section v-if="tasks.current" class="flex flex-wrap items-center gap-2" data-testid="task-actions">
            <template v-if="primaryActionFor(tasks.current)">
                <KButton
                    variant="primary"
                    :data-testid="`task-to-${primaryActionFor(tasks.current)}`"
                    @click="applyStatus(primaryActionFor(tasks.current) as TaskStatusValue)"
                >
                    {{ primaryLabelFor(primaryActionFor(tasks.current)) }}
                </KButton>
                <KButton
                    v-for="next in secondaryActionsFor(tasks.current)"
                    :key="next"
                    variant="secondary"
                    :data-testid="`task-to-${next}`"
                    @click="applyStatus(next)"
                >
                    {{ statusLabel(next) }}
                </KButton>
            </template>
            <KButton
                v-if="tasks.current.status === 'in_progress'"
                variant="secondary"
                data-testid="task-partial-complete"
                @click="partialComplete"
            >
                Partial complete
            </KButton>
        </section>

        <!-- Subtasks -->
        <section v-if="tasks.current" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="task-subtasks">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Subtasks</div>
            <form class="flex gap-2 mb-2" @submit.prevent="addSubtask">
                <input v-model="newSubtaskTitle" type="text" placeholder="Add subtask" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm flex-1" data-testid="subtask-title" />
                <button type="submit" class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1" data-testid="subtask-add">Add</button>
            </form>
            <div v-if="subtaskError" class="text-sm text-danger">{{ subtaskError }}</div>
            <ul class="space-y-1">
                <li v-for="sub in tasks.subtasks" :key="sub.id" class="flex items-center gap-2 text-sm" data-testid="subtask-item">
                    <input type="checkbox" :checked="sub.completed" class="accent-current" data-testid="subtask-toggle" @change="toggle(sub.id)" />
                    <span :class="sub.completed ? 'line-through text-gray-400' : ''">{{ sub.title }}</span>
                    <button type="button" class="ml-auto text-xs underline" data-testid="subtask-promote" @click="promote(sub.id)">Promote</button>
                </li>
                <li v-if="tasks.subtasks.length === 0" class="text-sm text-gray-500 dark:text-gray-400">No subtasks.</li>
            </ul>
        </section>

        <!-- Evidence attachments (FR-43) -->
        <AttachmentList
            v-if="tasks.current"
            :task-id="tasks.current.id"
            :completed="tasks.current.status === 'completed'"
        />
    </div>
</template>
