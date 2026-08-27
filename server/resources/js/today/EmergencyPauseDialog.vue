<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { todayApi } from './api';
import { useFocusTrap } from '../shell/focus-trap';
import KButton from '../components/KButton.vue';
import KIcon from '../components/KIcon.vue';
import type { EmergencyPauseResponse } from './types';

/**
 * Emergency Pause dialog (FR-07, design: Modal → ConfirmationDialog →
 * EmergencyPauseDialog). Lists the week's tasks, lets the user select which to
 * keep in place, and confirms the pause. All non-kept eligible tasks move +1
 * week; the week is tagged as an exceptional recovery period.
 */

const props = defineProps<{
    date: string;
    currentTaskId: number | null;
}>();

const emit = defineEmits<{
    confirmed: [result: EmergencyPauseResponse];
    cancelled: [];
}>();

interface KeepCandidate {
    taskId: number;
    title: string;
    date: string;
    start: string;
    end: string;
    locked: boolean;
}

const loading = ref(false);
const loadError = ref<string | null>(null);
const candidates = ref<KeepCandidate[]>([]);
const keepTaskIds = ref<Set<number>>(new Set());
const busy = ref(false);
const error = ref<string | null>(null);

const root = ref<HTMLElement | null>(null);
useFocusTrap(root, cancel);

onMounted(async () => {
    loading.value = true;
    loadError.value = null;
    try {
        const start = weekStart(props.date);
        const end = addDays(start, 6);
        const range = await todayApi.weekRange(start, end);

        const seen = new Set<number>();
        candidates.value = [];
        for (const event of range.events) {
            if (!event.task) {
                continue;
            }
            if (seen.has(event.task.id)) {
                continue;
            }
            seen.add(event.task.id);
            candidates.value.push({
                taskId: event.task.id,
                title: event.task.title,
                date: event.assignment.date,
                start: event.assignment.start_at,
                end: event.assignment.end_at,
                locked: event.locked,
            });
            // Default: keep the task currently being worked on.
            if (event.task.id === props.currentTaskId) {
                keepTaskIds.value.add(event.task.id);
            }
        }
    } catch (err) {
        loadError.value = (err as { message?: string }).message ?? 'Could not load this week\'s tasks.';
    } finally {
        loading.value = false;
    }
});

const weekLabel = computed(() => {
    const start = weekStart(props.date);
    return `${start} – ${addDays(start, 6)}`;
});

function formatLocal(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function weekStart(date: string): string {
    const d = new Date(`${date}T00:00:00`);
    const day = d.getDay();
    const diff = day === 0 ? 6 : day - 1; // Monday start
    const monday = new Date(d);
    monday.setDate(d.getDate() - diff);
    return formatLocal(monday);
}

function addDays(date: string, days: number): string {
    const d = new Date(`${date}T00:00:00`);
    d.setDate(d.getDate() + days);
    return formatLocal(d);
}

function toggleKeep(taskId: number): void {
    if (keepTaskIds.value.has(taskId)) {
        keepTaskIds.value.delete(taskId);
    } else {
        keepTaskIds.value.add(taskId);
    }
}

async function confirm(): Promise<void> {
    error.value = null;
    busy.value = true;
    try {
        const result = await todayApi.emergencyPause({
            date: props.date,
            keep_task_ids: [...keepTaskIds.value],
        });
        emit('confirmed', result);
    } catch (err) {
        error.value = (err as { message?: string }).message ?? 'Emergency Pause failed.';
    } finally {
        busy.value = false;
    }
}

function cancel(): void {
    emit('cancelled');
}
</script>

<template>
    <div ref="root" class="fixed inset-0 z-[var(--z-modal)] flex items-center justify-center bg-bg/80 p-4 backdrop-blur-[2px]" role="dialog" aria-modal="true" aria-labelledby="ep-title" data-testid="emergency-pause-dialog" @click.self="cancel">
        <div class="surface-hero w-full max-w-lg p-6 sm:p-8">
            <header class="mb-5 flex items-start gap-4 border-b border-border/20 pb-3">
                <div class="min-w-0">
                    <div class="text-[11px] font-semibold text-text-muted">Recovery week</div>
                    <h2 id="ep-title" class="text-lg font-bold" data-testid="ep-title">Emergency Pause</h2>
                </div>
                <button
                    type="button"
                    class="ml-auto rounded-sm p-1 transition-colors hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    aria-label="Close"
                    @click="cancel"
                ><KIcon name="x-mark" :size="18" /></button>
            </header>
            <p class="text-sm text-text-muted">
                Tag the week <span class="font-mono" data-testid="ep-week">{{ weekLabel }}</span> as an exceptional recovery
                period. Tasks you keep stay in place; every other eligible task moves to the same weekday next week.
            </p>

            <div v-if="loading" class="mt-3 text-sm text-text-muted" data-testid="ep-loading">Loading this week&rsquo;s tasks…</div>
            <div v-if="loadError" class="text-sm text-danger mt-3" role="alert" data-testid="ep-load-error">{{ loadError }}</div>

            <ul v-if="!loading && !loadError" class="mt-3 max-h-64 overflow-y-auto rounded-sm border border-border/20 divide-y divide-border/20">
                <li v-for="c in candidates" :key="c.taskId" class="flex items-center gap-3 px-3 py-2">
                    <input
                        :id="`ep-keep-${c.taskId}`"
                        type="checkbox"
                        class="shrink-0 accent-primary"
                        :checked="keepTaskIds.has(c.taskId)"
                        :disabled="busy"
                        data-testid="ep-keep"
                        @change="toggleKeep(c.taskId)"
                    />
                    <label :for="`ep-keep-${c.taskId}`" class="flex-1 text-sm" :data-testid="`ep-candidate-${c.taskId}`">
                        <span class="font-medium">{{ c.title }}</span>
                        <span class="block text-xs text-text-muted">
                            {{ c.date }} {{ c.start.slice(11, 16) }}–{{ c.end.slice(11, 16) }}
                            <span v-if="c.locked"> · locked (never auto-moved)</span>
                        </span>
                    </label>
                </li>
                <li v-if="candidates.length === 0" class="px-3 py-2 text-sm text-text-muted" data-testid="ep-empty">
                    No tasks scheduled this week.
                </li>
            </ul>

            <div v-if="error" class="text-sm text-danger mt-3" role="alert" data-testid="ep-error">{{ error }}</div>

            <div class="mt-4 flex justify-end gap-3 pt-1">
                <KButton variant="secondary" :disabled="busy" data-testid="ep-cancel" @click="cancel">
                    Cancel
                </KButton>
                <KButton variant="danger" :disabled="busy || loading || loadError !== null" data-testid="ep-confirm" @click="confirm">
                    {{ busy ? 'Pausing…' : 'Confirm Emergency Pause' }}
                </KButton>
            </div>
        </div>
    </div>
</template>