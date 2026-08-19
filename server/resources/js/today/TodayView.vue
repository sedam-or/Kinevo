<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { todayApi } from './api';
import { useTodayStore } from './store';
import { useShellStore } from '../shell/store';
import VisualStateBadge from '../visualstate/VisualStateBadge.vue';
import { taskStates } from '../visualstate/derive';
import type { EmptySlot, HardLandscapeEvent, TodayEvent } from './types';

const props = defineProps<{
    date: string;
}>();

const today = useTodayStore();
const shell = useShellStore();

const now = ref(new Date());

const quickCaptureForm = reactive({
    title: '',
    priorityTier: 3,
    durationMinutes: null as number | null,
});

const quickError = ref<string | null>(null);

onMounted(() => {
    void today.load(props.date);
});

const formattedDate = computed(() => {
    const d = new Date(`${today.date ?? props.date}T00:00:00`);
    return d.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
});

const capacityStatus = computed(() => today.capacity?.status ?? 'ok');

const statusLabel = computed(() => shell.syncState);

const currentEvent = computed<TodayEvent | null>(() => {
    const nowMs = now.value.getTime();
    return [...today.events]
        .sort((a, b) => new Date(a.assignment.start_at).getTime() - new Date(b.assignment.start_at).getTime())
        .find((e) => {
            const start = new Date(e.assignment.start_at).getTime();
            const end = new Date(e.assignment.end_at).getTime();
            return nowMs >= start && nowMs < end;
        }) ?? null;
});

const nextEvent = computed<TodayEvent | null>(() => {
    const nowMs = now.value.getTime();
    return [...today.events]
        .filter((e) => new Date(e.assignment.start_at).getTime() >= nowMs)
        .sort((a, b) => new Date(a.assignment.start_at).getTime() - new Date(b.assignment.start_at).getTime())[0] ?? null;
});

const nowStates = computed(() => {
    if (!currentEvent.value) {
        return [];
    }
    return taskStates(
        {
            locked: currentEvent.value.locked,
            conflict: currentEvent.value.conflict,
            status: currentEvent.value.task?.status,
            dueAt: currentEvent.value.task?.due_at,
        },
        today.date ?? props.date,
    );
});

const sortedEvents = computed(() =>
    [...today.events].sort((a, b) => new Date(a.assignment.start_at).getTime() - new Date(b.assignment.start_at).getTime()),
);

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

function formatDuration(minutes: number): string {
    if (minutes < 60) {
        return `${minutes}m`;
    }
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m === 0 ? `${h}h` : `${h}h ${m}m`;
}

function minutesOfDay(iso: string): number {
    return (new Date(iso).getTime() - new Date(`${today.date ?? props.date}T00:00:00`).getTime()) / 60000;
}

function startPercent(startMinutes: number): number {
    return Math.max(0, Math.min(100, (startMinutes / 1440) * 100));
}

function widthPercent(startMinutes: number, durationMinutes: number): number {
    return Math.max(2, Math.min(100 - startPercent(startMinutes), (durationMinutes / 1440) * 100));
}

function eventPosition(e: TodayEvent): { left: string; width: string } {
    const start = minutesOfDay(e.assignment.start_at);
    return { left: `${startPercent(start)}%`, width: `${widthPercent(start, e.assignment.duration_minutes)}%` };
}

function slotPosition(slot: EmptySlot): { left: string; width: string } {
    const start = minutesOfDay(slot.start);
    return { left: `${startPercent(start)}%`, width: `${widthPercent(start, slot.duration_minutes)}%` };
}

function landscapePosition(e: HardLandscapeEvent): { left: string; width: string } {
    const startRaw = (e.start ?? e.start_at) as string | undefined;
    const endRaw = (e.end ?? e.end_at) as string | undefined;
    if (!startRaw || !endRaw) {
        return { left: '0%', width: '10%' };
    }
    const start = minutesOfDay(startRaw);
    const duration = (new Date(endRaw).getTime() - new Date(startRaw).getTime()) / 60000;
    return { left: `${startPercent(start)}%`, width: `${widthPercent(start, duration)}%` };
}

function contextLabel(e: TodayEvent): string {
    if (e.goal?.title) {
        return e.goal.title as string;
    }
    if (e.program?.name) {
        return e.program.name as string;
    }
    if (e.milestone?.title) {
        return e.milestone.title as string;
    }
    return '';
}

async function quickCapture(): Promise<void> {
    quickError.value = null;
    if (quickCaptureForm.title.trim() === '') {
        return;
    }
    try {
        await todayApi.quickCapture({
            title: quickCaptureForm.title.trim(),
            priority_tier: quickCaptureForm.priorityTier,
            duration_minutes: quickCaptureForm.durationMinutes,
            date: props.date,
        });
        quickCaptureForm.title = '';
        quickCaptureForm.durationMinutes = null;
        await today.load(props.date);
    } catch (err) {
        quickError.value = (err as { message?: string }).message ?? 'Quick capture failed.';
    }
}
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="today-view">
        <!-- Header: date + sync state -->
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold" data-testid="today-date">{{ formattedDate }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400" data-testid="today-sync">Status: {{ statusLabel }}</p>
            </div>
            <div
                class="text-sm rounded-sm px-2 py-1"
                :class="capacityStatus === 'overload' ? 'bg-[#fff2f2] dark:bg-[#1D0002] text-[#F53003]' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'"
                data-testid="today-capacity"
            >
                {{ today.capacity ? `${today.capacity.scheduled_minutes}m / ${today.capacity.available_minutes}m available` : 'No data' }}
                <span v-if="today.capacity?.overload_minutes"> ({{ today.capacity.overload_minutes }}m overload)</span>
            </div>
        </header>

        <!-- Loading / error -->
        <div v-if="today.loading" class="text-sm text-gray-500" data-testid="today-loading">Loading Today…</div>
        <div v-if="today.error" class="text-sm text-[#F53003]" role="alert" data-testid="today-error">{{ today.error.message }}</div>

        <!-- NOW card -->
        <section v-if="currentEvent" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="now-card">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Now</div>
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-semibold" data-testid="now-title">{{ currentEvent.task?.title ?? 'Untitled' }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ formatTime(currentEvent.assignment.start_at) }} – {{ formatTime(currentEvent.assignment.end_at) }}
                        · {{ formatDuration(currentEvent.assignment.duration_minutes) }}
                    </div>
                    <div v-if="contextLabel(currentEvent)" class="text-sm text-gray-600 dark:text-gray-400">{{ contextLabel(currentEvent) }}</div>
                    <div class="flex gap-2 mt-1 text-xs">
                        <VisualStateBadge v-for="s in nowStates" :key="s" :state="s" />
                    </div>
                </div>
                <button
                    type="button"
                    class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                    data-testid="now-complete"
                >
                    Complete
                </button>
            </div>
        </section>
        <section v-else-if="today.hasData" class="text-sm text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="no-now">
            No task in progress right now.
        </section>

        <!-- NEXT -->
        <section v-if="nextEvent" class="text-sm" data-testid="next-card">
            <span class="uppercase text-xs text-gray-500 dark:text-gray-400 mr-2">Next</span>
            {{ nextEvent.task?.title ?? 'Untitled' }} at {{ formatTime(nextEvent.assignment.start_at) }}
        </section>

        <!-- Timeline -->
        <section class="relative border border-gray-300 dark:border-gray-600 rounded-sm p-4 min-h-32" data-testid="today-timeline">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Timeline</div>

            <!-- Hard Landscape -->
            <div
                v-for="hl in today.hardLandscape"
                :key="hl.id"
                class="absolute top-8 bottom-8 rounded-sm bg-gray-200 dark:bg-gray-700 opacity-60"
                :style="landscapePosition(hl)"
                :title="hl.title ?? 'Hard landscape'"
                data-testid="timeline-landscape"
            ></div>

            <!-- Empty slots -->
            <div
                v-for="(slot, i) in today.emptySlots"
                :key="i"
                class="absolute top-8 h-2 bg-green-200 dark:bg-green-800 rounded-sm"
                :style="slotPosition(slot)"
                :title="`Empty: ${formatTime(slot.start)}–${formatTime(slot.end)}`"
                data-testid="timeline-empty"
            ></div>

            <!-- Scheduled events -->
            <div
                v-for="e in sortedEvents"
                :key="e.assignment.id"
                class="absolute top-12 rounded-sm px-2 py-1 overflow-hidden text-xs"
                :class="e.locked ? 'bg-blue-200 dark:bg-blue-800' : e.conflict ? 'bg-[#fff2f2] dark:bg-[#1D0002] text-[#F53003]' : 'bg-gray-100 dark:bg-gray-800'"
                :style="eventPosition(e)"
                :title="`${e.task?.title ?? 'Untitled'} ${formatTime(e.assignment.start_at)}–${formatTime(e.assignment.end_at)}`"
                data-testid="timeline-event"
            >
                {{ formatTime(e.assignment.start_at) }} {{ e.task?.title ?? 'Untitled' }}
            </div>

            <div class="absolute bottom-2 left-4 right-4 flex justify-between text-[10px] text-gray-400">
                <span>06:00</span>
                <span>12:00</span>
                <span>18:00</span>
                <span>24:00</span>
            </div>
        </section>

        <!-- Quick Capture -->
        <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="quick-capture">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Quick Capture</div>
            <form class="flex flex-col gap-3" @submit.prevent="quickCapture">
                <div v-if="quickError" class="text-sm text-[#F53003]" role="alert">{{ quickError }}</div>
                <label class="flex flex-col gap-1 text-sm">
                    Title
                    <input v-model="quickCaptureForm.title" type="text" required class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="qc-title" />
                </label>
                <div class="flex gap-3">
                    <label class="flex flex-col gap-1 text-sm">
                        Priority
                        <select v-model.number="quickCaptureForm.priorityTier" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="qc-priority">
                            <option :value="1">High</option>
                            <option :value="2">Medium</option>
                            <option :value="3">Low</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        Duration (min)
                        <input v-model.number="quickCaptureForm.durationMinutes" type="number" min="1" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="qc-duration" />
                    </label>
                </div>
                <button type="submit" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 font-medium" data-testid="qc-submit">
                    Capture
                </button>
            </form>
        </section>
    </div>
</template>
