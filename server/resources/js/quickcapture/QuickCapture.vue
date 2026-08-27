<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import { useQuickCaptureStore } from './store';
import { useFocusTrap } from '../shell/focus-trap';
import KButton from '../components/KButton.vue';
import KIcon from '../components/KIcon.vue';
import KInput from '../components/KInput.vue';

const emit = defineEmits<{
    (e: 'created', taskId: number): void;
    (e: 'scheduleLater', taskId: number): void;
}>();

// Native controls follow the theme tokens (TASK-P17-013). Number- and
// null-coercion fields (v-model.number, :value="null") stay on native
// elements deliberately: wire payloads must remain integers/null.
const fieldClass = 'border border-border rounded-sm bg-bg px-3 py-2 text-sm text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus';

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
    <div ref="root" role="dialog" aria-modal="true" aria-labelledby="qc-title" class="fixed inset-0 z-[var(--z-modal)] flex items-center justify-center bg-bg/80 p-4 backdrop-blur-[2px]" data-testid="quick-capture-modal" @click.self="close">
        <div class="surface-hero w-full max-w-lg p-6 sm:p-8">
            <header class="flex items-center justify-between border-b border-border/20 pb-3 mb-5">
                <h2 id="qc-title" class="text-lg font-bold">Quick Capture</h2>
                <button type="button" class="ml-auto rounded-sm p-1 transition-colors hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-focus" data-testid="qc-close" aria-label="Close" @click="close"><KIcon name="x-mark" :size="18" /></button>
            </header>

            <div v-if="localError" class="text-sm text-danger mb-3" role="alert">{{ localError }}</div>
            <div v-if="qc.error && !localError" class="text-sm text-danger mb-3" role="alert">{{ qc.error.message }}</div>

            <!-- Capture form -->
            <form v-if="!qc.lastResult" class="flex flex-col gap-3" @submit.prevent="submit" data-testid="qc-form">
                <label class="flex flex-col gap-1 text-sm font-semibold">
                    Title
                    <KInput v-model="form.title" type="text" required data-testid="qc-title" />
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1 text-sm font-semibold">
                        Priority
                        <select v-model.number="form.priorityTier" :class="fieldClass" data-testid="qc-priority">
                            <option :value="1">High</option>
                            <option :value="2">Medium</option>
                            <option :value="3">Low</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-semibold">
                        Size
                        <select v-model="form.size" :class="fieldClass" data-testid="qc-size">
                            <option value="cepat">Cepat</option>
                            <option value="sedang">Sedang</option>
                            <option value="berat">Berat</option>
                        </select>
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1 text-sm font-semibold">
                        Duration (min)
                        <input v-model.number="form.durationMinutes" type="number" min="1" :class="fieldClass" data-testid="qc-duration" />
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-semibold">
                        Due date
                        <input v-model="form.dueDate" type="date" :class="fieldClass" data-testid="qc-date" />
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1 text-sm font-semibold">
                        Program
                        <select v-model="form.programId" :class="fieldClass" data-testid="qc-program">
                            <option :value="null">None</option>
                            <option v-for="p in qc.programs" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-semibold">
                        Goal
                        <select v-model="form.goalId" :class="fieldClass" data-testid="qc-goal">
                            <option :value="null">None</option>
                            <option v-for="g in qc.goals" :key="g.id" :value="g.id">{{ g.title }}</option>
                        </select>
                    </label>
                </div>
                <label v-if="form.goalId !== null" class="flex flex-col gap-1 text-sm font-semibold">
                    Milestone
                    <select v-model="form.milestoneId" :class="fieldClass" data-testid="qc-milestone">
                        <option :value="null">None</option>
                        <option v-for="m in qc.milestones" :key="m.id" :value="m.id">{{ m.title }}</option>
                    </select>
                </label>
                <KButton type="submit" variant="primary" class="w-full mt-1" :disabled="qc.busy" data-testid="qc-submit">
                    {{ qc.busy ? 'Capturing…' : 'Capture' }}
                </KButton>
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
                <KButton variant="secondary" class="w-full" data-testid="qc-done" @click="qc.hide()">Done</KButton>
            </div>

            <!-- No capacity: three actionable strategies -->
            <div v-else data-testid="qc-no-capacity">
                <p class="text-sm mb-3">
                    No free slot today for “{{ qc.lastResult.task.title }}”. The task was saved — choose how to place it.
                </p>
                <div class="flex flex-col gap-2">
                    <KButton variant="secondary" class="w-full justify-start" :disabled="qc.busy" data-testid="qc-auto-swap" @click="runAutoSwap">
                        <span class="flex-1 text-left">Auto Swap — move a lower-priority task to free a slot</span>
                    </KButton>
                    <KButton variant="secondary" class="w-full justify-start" data-testid="qc-manual-swap" @click="qc.hide()">
                        <span class="flex-1 text-left">Manual Swap — adjust the schedule yourself</span>
                    </KButton>
                    <KButton variant="secondary" class="w-full justify-start" data-testid="qc-schedule-later" @click="scheduleLater">
                        <span class="flex-1 text-left">Schedule Later — keep it in your backlog</span>
                    </KButton>
                </div>
                <div v-if="qc.autoSwapResult && !qc.autoSwapResult.applied" class="text-sm text-danger mt-3" data-testid="qc-swap-failed">
                    {{ qc.autoSwapResult.explanation }}
                </div>
            </div>
        </div>
    </div>
</template>
