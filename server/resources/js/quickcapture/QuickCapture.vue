<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import { useQuickCaptureStore } from './store';
import { useFocusTrap } from '../shell/focus-trap';

const emit = defineEmits<{
    (e: 'created', taskId: number): void;
    (e: 'scheduleLater', taskId: number): void;
}>();

const qc = useQuickCaptureStore();

const form = reactive({
    title: '',
    priorityTier: 3,
    size: 'sedang' as 'cepat' | 'sedang' | 'berat',
    durationMinutes: null as number | null,
    programId: null as number | null,
    goalId: null as number | null,
    milestoneId: null as number | null,
    dueDate: '',
});

const localError = ref<string | null>(null);

onMounted(() => {
    void qc.loadContext();
});

watch(
    () => form.goalId,
    (goalId) => {
        form.milestoneId = null;
        if (goalId !== null) {
            void qc.loadMilestones(goalId);
        } else {
            qc.clearMilestones();
        }
    },
);

function durationFromSize(size: string): number | null {
    const map: Record<string, number | null> = { cepat: 15, sedang: 45, berat: 90 };
    return map[size] ?? null;
}

function todayDate(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

async function submit(): Promise<void> {
    localError.value = null;
    if (form.title.trim() === '') {
        return;
    }
    const result = await qc.submit({
        title: form.title.trim(),
        priority_tier: form.priorityTier,
        size: form.size,
        duration_minutes: form.durationMinutes ?? durationFromSize(form.size),
        program_id: form.programId,
        goal_id: form.goalId,
        date: form.dueDate === '' ? todayDate() : form.dueDate,
    });
    if (result === null) {
        localError.value = qc.error?.message ?? 'Quick capture failed.';
    }
}

async function runAutoSwap(): Promise<void> {
    if (!qc.lastResult) {
        return;
    }
    const task = qc.lastResult.task;
    const date = form.dueDate === '' ? todayDate() : form.dueDate;
    const duration = form.durationMinutes ?? durationFromSize(form.size) ?? 45;
    await qc.autoSwap(task.id, date, duration);
    if (qc.autoSwapResult?.applied) {
        emit('created', task.id);
        qc.hide();
    }
}

function scheduleLater(): void {
    if (qc.lastResult) {
        emit('scheduleLater', qc.lastResult.task.id);
    }
    qc.hide();
}

function close(): void {
    if (!qc.busy) {
        qc.hide();
    }
}

// Modal a11y parity with the Today dialogs (design.md §52, WCAG 2.2):
// initial focus, Tab wrap, Escape close, focus restore.
const root = ref<HTMLElement | null>(null);
useFocusTrap(root, close);
</script>

<template>
    <div ref="root" role="dialog" aria-modal="true" aria-labelledby="qc-title" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" data-testid="quick-capture-modal" @click.self="close">
        <div class="bg-[#FDFDFC] dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] border border-gray-300 dark:border-gray-600 rounded-sm w-full max-w-lg p-5">
            <header class="flex items-center justify-between mb-4">
                <h2 id="qc-title" class="text-lg font-semibold">Quick Capture</h2>
                <button type="button" class="text-sm underline" data-testid="qc-close" @click="close">Close</button>
            </header>

            <div v-if="localError" class="text-sm text-danger mb-3" role="alert">{{ localError }}</div>
            <div v-if="qc.error && !localError" class="text-sm text-danger mb-3" role="alert">{{ qc.error.message }}</div>

            <!-- Capture form -->
            <form v-if="!qc.lastResult" class="flex flex-col gap-3" @submit.prevent="submit" data-testid="qc-form">
                <label class="flex flex-col gap-1 text-sm">
                    Title
                    <input v-model="form.title" type="text" required class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="qc-title" />
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1 text-sm">
                        Priority
                        <select v-model.number="form.priorityTier" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="qc-priority">
                            <option :value="1">High</option>
                            <option :value="2">Medium</option>
                            <option :value="3">Low</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        Size
                        <select v-model="form.size" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="qc-size">
                            <option value="cepat">Cepat</option>
                            <option value="sedang">Sedang</option>
                            <option value="berat">Berat</option>
                        </select>
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1 text-sm">
                        Duration (min)
                        <input v-model.number="form.durationMinutes" type="number" min="1" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="qc-duration" />
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        Due date
                        <input v-model="form.dueDate" type="date" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="qc-date" />
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1 text-sm">
                        Program
                        <select v-model="form.programId" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="qc-program">
                            <option :value="null">—</option>
                            <option v-for="p in qc.programs" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        Goal
                        <select v-model="form.goalId" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="qc-goal">
                            <option :value="null">—</option>
                            <option v-for="g in qc.goals" :key="g.id" :value="g.id">{{ g.title }}</option>
                        </select>
                    </label>
                </div>
                <label v-if="form.goalId !== null" class="flex flex-col gap-1 text-sm">
                    Milestone
                    <select v-model="form.milestoneId" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="qc-milestone">
                        <option :value="null">—</option>
                        <option v-for="m in qc.milestones" :key="m.id" :value="m.id">{{ m.title }}</option>
                    </select>
                </label>
                <button type="submit" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 font-medium text-gray-900 dark:text-gray-100" :disabled="qc.busy" data-testid="qc-submit">
                    {{ qc.busy ? 'Capturing…' : 'Capture' }}
                </button>
            </form>

            <!-- Placed -->
            <div v-else-if="qc.lastResult.placed" data-testid="qc-placed">
                <p class="text-sm mb-3">
                    Captured “{{ qc.lastResult.task.title }}”.
                    <template v-if="qc.lastResult.assignment">
                        Scheduled {{ qc.lastResult.assignment.date }} at
                        {{ new Date(qc.lastResult.assignment.start_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}.
                    </template>
                </p>
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2" data-testid="qc-done" @click="qc.hide()">Done</button>
            </div>

            <!-- No capacity: three actionable strategies -->
            <div v-else data-testid="qc-no-capacity">
                <p class="text-sm mb-3">
                    No free slot today for “{{ qc.lastResult.task.title }}”. The task was saved — choose how to place it.
                </p>
                <div class="flex flex-col gap-2">
                    <button
                        type="button"
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 text-left"
                        data-testid="qc-auto-swap"
                        :disabled="qc.busy"
                        @click="runAutoSwap"
                    >
                        Auto Swap — move a lower-priority task to free a slot
                    </button>
                    <button
                        type="button"
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 text-left"
                        data-testid="qc-manual-swap"
                        @click="qc.hide()"
                    >
                        Manual Swap — adjust the schedule yourself
                    </button>
                    <button
                        type="button"
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 text-left"
                        data-testid="qc-schedule-later"
                        @click="scheduleLater"
                    >
                        Schedule Later — keep it in your backlog
                    </button>
                </div>
                <div v-if="qc.autoSwapResult && !qc.autoSwapResult.applied" class="text-sm text-danger mt-3" data-testid="qc-swap-failed">
                    {{ qc.autoSwapResult.explanation }}
                </div>
            </div>
        </div>
    </div>
</template>
