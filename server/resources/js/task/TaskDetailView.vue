<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import { useTaskStore } from './store';
import { TASK_TRANSITIONS, type Task, type TaskStatusValue } from './types';
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

function transitionsFor(task: Task | null): TaskStatusValue[] {
    if (!task) {
        return [];
    }
    return TASK_TRANSITIONS[task.status as TaskStatusValue] ?? [];
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
        <div v-if="tasks.error" class="text-sm text-[#F53003]" role="alert" data-testid="task-detail-error">{{ tasks.error.message }}</div>

        <!-- Edit form -->
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

        <!-- Status transitions + partial completion -->
        <section v-if="tasks.current" class="flex gap-2 flex-wrap" data-testid="task-actions">
            <button
                v-for="next in transitionsFor(tasks.current)"
                :key="next"
                type="button"
                class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1"
                :data-testid="`task-to-${next}`"
                @click="applyStatus(next)"
            >
                {{ statusLabel(next) }}
            </button>
            <button
                v-if="tasks.current.status === 'in_progress'"
                type="button"
                class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1"
                data-testid="task-partial-complete"
                @click="partialComplete"
            >
                Partial complete
            </button>
        </section>

        <!-- Subtasks -->
        <section v-if="tasks.current" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="task-subtasks">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Subtasks</div>
            <form class="flex gap-2 mb-2" @submit.prevent="addSubtask">
                <input v-model="newSubtaskTitle" type="text" placeholder="Add subtask" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm flex-1" data-testid="subtask-title" />
                <button type="submit" class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1" data-testid="subtask-add">Add</button>
            </form>
            <div v-if="subtaskError" class="text-sm text-[#F53003]">{{ subtaskError }}</div>
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
