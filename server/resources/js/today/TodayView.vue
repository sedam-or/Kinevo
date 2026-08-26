<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { todayApi } from './api';
import { useTodayStore } from './store';
import { useShellStore } from '../shell/store';
import { useWorkspaceStore } from '../workspace/store';
import VisualStateBadge from '../visualstate/VisualStateBadge.vue';
import ExecutionTimer from '../execution/ExecutionTimer.vue';
import RechargeTimer from '../recharge/RechargeTimer.vue';
import EmergencyPauseDialog from './EmergencyPauseDialog.vue';
import BreakModeDialog from './BreakModeDialog.vue';
import BoostDialog from './BoostDialog.vue';
import { taskStates } from '../visualstate/derive';
import KButton from '../components/KButton.vue';
import FeatureHelp from '../components/FeatureHelp.vue';
import { useToastStore } from '../components/toast';
import WhyThis from '../components/WhyThis.vue';
import { useAdaptiveStore } from '../adaptive/store';
import AdaptiveContextPanel from '../adaptive/AdaptiveContextPanel.vue';
import type { EmptySlot, EmergencyPauseResponse, EndBreakResponse, EndBoostTargetResponse, HardLandscapeEvent, SetBoostTargetResponse, StartBreakResponse, TodayEvent } from './types';

const props = defineProps<{
    date: string;
}>();

const today = useTodayStore();
const workspaces = useWorkspaceStore();
const shell = useShellStore();

/** §22: capacity as a share of the available envelope (0-100). */
const capacityPercent = computed(() => {
    if (!today.capacity || today.capacity.available_minutes <= 0) {
        return 0;
    }
    const total = today.capacity.scheduled_minutes + today.capacity.available_minutes;
    if (total <= 0) {
        return 0;
    }
    return Math.round((today.capacity.scheduled_minutes / total) * 100);
});

const capacityRevealed = ref(false);

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

const toast = useToastStore();
const adaptive = useAdaptiveStore();
// Today's progress (TASK-P17-014): the §99 loop ends in PROGRESS — surface
// completed-vs-planned for the day as supporting context under the timeline.
const completedCount = computed(() => today.events.filter((e) => e.task?.status === 'completed').length);
const progressPercent = computed(() =>
    today.events.length === 0 ? 0 : Math.round((completedCount.value / today.events.length) * 100),
);

const nextEmphasis = ref(false);
let emphasisTimer: ReturnType<typeof setTimeout> | undefined;

function onExecutionCompleted(): void {
    // Complete cascade (TASK-P17-011): the data reloads below (progress
    // advance); announce it and spotlight what's next.
    toast.push('Task completed · progress updated');
    nextEmphasis.value = true;
    if (emphasisTimer) {
        clearTimeout(emphasisTimer);
    }
    emphasisTimer = setTimeout(() => {
        nextEmphasis.value = false;
    }, 2000);
    // A completed timer may have changed the task status (completed/continued);
    // reload the day so the schedule, states, and NOW card reflect it.
    void today.load(props.date);
}

function onRechargeCompleted(): void {
    // A completed recharge updates the day's Work-Life Ratio; reload Today.
    void today.load(props.date);
}

const miniPauseBusy = ref(false);
const miniPauseError = ref<string | null>(null);
const miniPauseMessage = ref<string | null>(null);

async function miniPause(): Promise<void> {
    miniPauseError.value = null;
    miniPauseMessage.value = null;
    miniPauseBusy.value = true;
    try {
        const result = await todayApi.miniPause({ date: props.date });
        miniPauseMessage.value = result.explanation;
        // The schedule changed (moves today's tasks to the next day); reload.
        await today.load(props.date);
    } catch (err) {
        miniPauseError.value = (err as { message?: string }).message ?? 'Mini Pause failed.';
    } finally {
        miniPauseBusy.value = false;
    }
}

const emergencyDialogOpen = ref(false);
const emergencyMessage = ref<string | null>(null);
const emergencyError = ref<string | null>(null);

function onEmergencyConfirmed(result: EmergencyPauseResponse): void {
    emergencyDialogOpen.value = false;
    emergencyMessage.value = result.explanation;
    emergencyError.value = null;
    // The week is now tagged exceptional and tasks moved; reload so the
    // schedule, recovery badge, and NOW card reflect it.
    void today.load(props.date);
}

function onEmergencyCancelled(): void {
    emergencyDialogOpen.value = false;
}

function openEmergencyDialog(): void {
    emergencyDialogOpen.value = true;
}

const breakDialogOpen = ref(false);
const breakMessage = ref<string | null>(null);
const breakError = ref<string | null>(null);
const endBreakBusy = ref(false);

function onBreakConfirmed(result: StartBreakResponse): void {
    breakDialogOpen.value = false;
    breakMessage.value = result.explanation;
    breakError.value = null;
    void today.load(props.date);
}

function onBreakCancelled(): void {
    breakDialogOpen.value = false;
}

function openBreakDialog(): void {
    breakDialogOpen.value = true;
}

async function endBreak(): Promise<void> {
    breakError.value = null;
    endBreakBusy.value = true;
    try {
        const result: EndBreakResponse = await todayApi.endBreak();
        breakMessage.value = result.explanation;
        void today.load(props.date);
    } catch (err) {
        breakError.value = (err as { message?: string }).message ?? 'Break Mode could not be ended.';
    } finally {
        endBreakBusy.value = false;
    }
}

const boostDialogOpen = ref(false);
const boostMessage = ref<string | null>(null);
const boostError = ref<string | null>(null);

function onBoostSaved(result: SetBoostTargetResponse): void {
    boostDialogOpen.value = false;
    boostMessage.value = result.explanation;
    boostError.value = null;
    void today.load(props.date);
}

function onBoostCancelled(): void {
    boostDialogOpen.value = false;
}

function openBoostDialog(): void {
    boostDialogOpen.value = true;
}

async function endBoostTarget(): Promise<void> {
    boostError.value = null;
    try {
        const result: EndBoostTargetResponse = await todayApi.endBoostTarget();
        boostMessage.value = result.explanation;
        void today.load(props.date);
    } catch (err) {
        boostError.value = (err as { message?: string }).message ?? 'Boost target could not be ended.';
    }
}
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="today-view">
        <!-- Header: date + sync state -->
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold" data-testid="today-date">{{ formattedDate }}</h1>
                <p class="text-sm flex items-center gap-2">
                    <span
                        v-if="workspaces.activeWorkspace"
                        class="inline-flex items-center gap-1 rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs"
                        data-testid="today-workspace-chip"
                    >
                        <span v-if="workspaces.activeWorkspace.accent" class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: workspaces.activeWorkspace.accent }" aria-hidden="true" />
                        {{ workspaces.activeWorkspace.name }}
                    </span>
                    <span v-else class="text-gray-500 dark:text-gray-400" data-testid="today-sync">Status: {{ statusLabel }}</span>
                </p>
            </div>
            <!-- Capacity feedback (design.md §22): a load bar; click reveals details. -->
            <div class="flex flex-col items-end gap-1" data-testid="today-capacity" :class="{ 'cursor-pointer': today.capacity }" @click="today.capacity && (capacityRevealed = !capacityRevealed)">
                <div class="flex items-center gap-2 text-sm">
                    <FeatureHelp id="capacity" title="Capacity" body="How full today is compared with the time you actually have. Overload means the plan needs a cut before the day cuts it for you." />
                    <span :class="capacityStatus === 'overload' ? 'text-danger' : 'text-gray-600 dark:text-gray-300'">
                        {{ today.capacity ? `${capacityPercent}% of capacity used` : 'No capacity data' }}
                    </span>
                    <span v-if="today.capacity?.overload_minutes" class="text-xs text-danger">({{ today.capacity.overload_minutes }}m overload)</span>
                </div>
                <div v-if="today.capacity" class="w-40 h-2 rounded-sm bg-gray-200 dark:bg-gray-700 overflow-hidden" role="img" :aria-label="`${capacityPercent}% capacity used`">
                    <div
                        class="h-full transition-all"
                        :class="capacityStatus === 'overload' ? 'bg-[#F53003]' : 'bg-[#2C5FA8]'"
                        :style="{ width: `${capacityPercent}%` }"
                    ></div>
                </div>
                <dl v-if="today.capacity && capacityRevealed" class="text-xs text-gray-600 dark:text-gray-400 text-right space-y-0.5">
                    <div><dt class="inline">Scheduled load:</dt> <dd class="inline">{{ today.capacity.scheduled_minutes }}m</dd></div>
                    <div><dt class="inline">Available capacity:</dt> <dd class="inline">{{ today.capacity.available_minutes }}m</dd></div>
                    <div v-if="today.capacity.overload_minutes"><dt class="inline">Overload:</dt> <dd class="inline">{{ today.capacity.overload_minutes }}m</dd></div>
                </dl>
            </div>
        </header>

        <!-- Loading / error -->
        <div v-if="today.loading" class="text-sm text-gray-500" data-testid="today-loading">Loading Today…</div>
        <div v-if="today.error" class="text-sm text-danger" role="alert" data-testid="today-error">{{ today.error.message }}</div>

        <!-- Emergency Pause recovery banner (FR-07): the week is exceptional. -->
        <section
            v-if="today.pause"
            class="border border-gray-300 dark:border-gray-600 rounded-sm p-4 bg-gray-100 dark:bg-gray-800"
            data-testid="recovery-banner"
        >
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Recovery week</div>
            <p class="text-sm">
                This week is tagged as an exceptional recovery period
                ({{ today.pause.week_start }} to {{ today.pause.week_end }}). Notifications are suppressed and this
                week is excluded from capacity estimates.
            </p>
        </section>

        <!-- Break Mode banner (FR-36/FR-49): an active break covers this week. -->
        <section
            v-if="today.breakPeriod"
            class="border border-gray-300 dark:border-gray-600 rounded-sm p-4 bg-gray-100 dark:bg-gray-800"
            data-testid="break-banner"
        >
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Break Mode</div>
            <p class="text-sm">
                You are on break ({{ today.breakPeriod.start_date }} to {{ today.breakPeriod.end_date }}).
                Notifications are suppressed and the covered weeks are excluded from capacity estimates.
            </p>
            <div class="mt-3 flex items-center justify-between gap-2">
                <span class="text-sm text-gray-600 dark:text-gray-400">End the break to resume scheduling.</span>
                <button
                    type="button"
                    class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                    :disabled="endBreakBusy"
                    data-testid="end-break-button"
                    @click="endBreak"
                >
                    {{ endBreakBusy ? 'Ending…' : 'End Break' }}
                </button>
            </div>
            <div class="mt-3 border-t border-gray-300 dark:border-gray-600 pt-3">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Boost Mode (FR-37/FR-38)</div>
                <p v-if="boostMessage" class="text-sm" data-testid="boost-message">{{ boostMessage }}</p>
                <p v-else-if="boostError" class="text-sm text-danger" role="alert" data-testid="boost-error">{{ boostError }}</p>
                <div class="flex justify-end gap-2 mt-2">
                    <button
                        type="button"
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                        data-testid="boost-mode-button"
                        @click="openBoostDialog"
                    >
                        Boost Mode
                    </button>
                    <button
                        type="button"
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                        data-testid="end-boost-button"
                        @click="endBoostTarget"
                    >
                        End Boost
                    </button>
                </div>
            </div>
        </section>

        <!-- NOW card (design.md §12.2: highest-priority surface — thick border, offset shadow, large title/time) -->
        <section
            v-if="currentEvent"
            class="border-2 border-gray-300 dark:border-gray-600 rounded-sm p-5 shadow-[4px_4px_0_rgba(0,0,0,0.06)] dark:shadow-[4px_4px_0_rgba(255,255,255,0.06)]"
            :class="{ 'border-[var(--color-primary)] dark:border-[var(--color-primary)]': currentEvent.locked }"
            data-testid="now-card"
        >
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Now</div>
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-lg font-semibold leading-snug" data-testid="now-title">{{ currentEvent.task?.title ?? 'Untitled' }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ formatTime(currentEvent.assignment.start_at) }} – {{ formatTime(currentEvent.assignment.end_at) }}
                        · {{ formatDuration(currentEvent.assignment.duration_minutes) }}
                    </div>
                    <div v-if="contextLabel(currentEvent)" class="text-sm text-gray-600 dark:text-gray-400">{{ contextLabel(currentEvent) }}</div>
                    <div class="flex gap-2 mt-1 text-xs">
                        <VisualStateBadge v-for="s in nowStates" :key="s" :state="s" />
                        <!-- Workflow continuity (TASK-P17-002): the executing task
                             links back to the goal it serves. -->
                        <button
                            v-if="currentEvent.task?.goal_id"
                            type="button"
                            class="underline text-gray-600 dark:text-gray-300 hover:text-[var(--color-text)]"
                            data-testid="now-goal-link"
                            @click="shell.setView('goals', currentEvent.task!.goal_id!)"
                        >
                            Goal ↗
                        </button>
                    </div>
                    <!-- FR-63: expandable scheduling explanation, collapsed by
                         default so the NOW card stays uncluttered (P17-015). -->
                    <WhyThis
                        v-if="currentEvent.task"
                        class="mt-1"
                        :task="{ priority_tier: currentEvent.task.priority_tier, due_at: currentEvent.task.due_at, estimated_minutes: currentEvent.task.estimated_minutes }"
                        :assignment="currentEvent.assignment"
                        :energy-note="adaptive.latest?.energy_level != null ? `Latest energy check-in (${adaptive.latest.energy_level}/10) informed today's ordering.` : null"
                    />
                </div>
                <ExecutionTimer
                    v-if="currentEvent.task"
                    :task-id="currentEvent.task.id"
                    :task-title="currentEvent.task.title"
                    data-testid="now-execution"
                    @completed="onExecutionCompleted"
                />
            </div>
            <RechargeTimer
                class="mt-3"
                :date="today.date ?? props.date"
                data-testid="now-recharge"
                @completed="onRechargeCompleted"
            />
            <div class="mt-3 border-t border-gray-200 dark:border-gray-700 pt-3 flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <span v-if="miniPauseMessage" data-testid="mini-pause-message">{{ miniPauseMessage }}</span>
                    <span v-else>Move today's remaining tasks to tomorrow.</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <KButton
                        variant="secondary"
                        :disabled="miniPauseBusy"
                        data-testid="mini-pause-button"
                        @click="miniPause"
                    >
                        {{ miniPauseBusy ? 'Pausing…' : 'Mini Pause' }}
                    </KButton>
                    <KButton
                        variant="danger"
                        data-testid="emergency-pause-button"
                        @click="openEmergencyDialog"
                    >
                        Emergency Pause
                    </KButton>
                    <KButton
                        variant="secondary"
                        data-testid="break-mode-button"
                        @click="openBreakDialog"
                    >
                        Break Mode
                    </KButton>
                </div>
            </div>
            <div v-if="miniPauseError" class="text-sm text-danger" role="alert" data-testid="mini-pause-error">
                {{ miniPauseError }}
            </div>
            <div v-if="emergencyMessage" class="text-sm text-gray-600 dark:text-gray-400 mt-2" data-testid="emergency-pause-message">
                {{ emergencyMessage }}
            </div>
            <div v-if="emergencyError" class="text-sm text-danger" role="alert" data-testid="emergency-pause-error">
                {{ emergencyError }}
            </div>
            <div v-if="breakMessage" class="text-sm text-gray-600 dark:text-gray-400 mt-2" data-testid="break-message">
                {{ breakMessage }}
            </div>
            <div v-if="breakError" class="text-sm text-danger" role="alert" data-testid="break-error">
                {{ breakError }}
            </div>
        </section>
        <section v-else-if="today.hasData" class="flex flex-col gap-2 text-sm text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="no-now">
            <span>No task in progress right now.</span>
            <FeatureHelp
                id="today-flow"
                variant="block"
                title="How Today works"
                body="Start the top task, complete it, and your progress updates itself. Kinevo always lines up what's next."
            />
        </section>

        <!-- NEXT -->
        <section v-if="nextEvent" class="text-sm rounded-sm transition-shadow" :class="nextEmphasis ? 'ring-2 ring-[var(--color-primary)] p-2' : 'p-2'" data-testid="next-card">
            <span class="uppercase text-xs text-gray-500 dark:text-gray-400 mr-2">Next</span>
            {{ nextEvent.task?.title ?? 'Untitled' }} at {{ formatTime(nextEvent.assignment.start_at) }}
        </section>

        <!-- Timeline -->
        <section class="relative border border-gray-300 dark:border-gray-600 rounded-sm p-4 min-h-32" data-testid="today-timeline">
            <div class="flex items-center gap-2 mb-2">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Timeline</div>
                <FeatureHelp id="hard-landscape" title="Hard Landscape" body="Fixed commitments — appointments, travel, sacred anchors — that block the timeline. Kinevo schedules around them; they never move on their own." />
            </div>

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
                :class="e.locked ? 'bg-blue-200 dark:bg-blue-800' : e.conflict ? 'bg-[#fff2f2] dark:bg-[#1D0002] text-danger' : 'bg-gray-100 dark:bg-gray-800'"
                :style="eventPosition(e)"
                :title="`${e.task?.title ?? 'Untitled'} ${formatTime(e.assignment.start_at)}–${formatTime(e.assignment.end_at)}`"
                data-testid="timeline-event"
            >
                {{ formatTime(e.assignment.start_at) }} {{ e.task?.title ?? 'Untitled' }}
            </div>

            <div class="absolute bottom-2 left-4 right-4 flex justify-between text-[10px] text-gray-600 dark:text-gray-400">
                <span>06:00</span>
                <span>12:00</span>
                <span>18:00</span>
                <span>24:00</span>
            </div>
        </section>

        <!-- Supporting context (TASK-P17-014): progress → check-in → capture.
             Strict hierarchy per design.md §104: NOW → NEXT → Timeline →
             context; nothing above competes with the execution hub. -->
        <section v-if="today.events.length > 0" class="flex items-center gap-3" data-testid="today-progress">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 shrink-0">Today's progress</div>
            <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-sm overflow-hidden" aria-hidden="true">
                <div class="h-full bg-[var(--color-primary)] transition-all" :style="{ width: progressPercent + '%' }"></div>
            </div>
            <span class="text-xs text-gray-600 dark:text-gray-400 tabular-nums" data-testid="today-progress-count">
                {{ completedCount }}/{{ today.events.length }} done
            </span>
        </section>

        <!-- Lightweight adaptive-context check-in (design.md §23) -->
        <AdaptiveContextPanel v-if="!today.loading && !today.error && today.date" />

        <!-- Quick Capture -->
        <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="quick-capture">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Quick Capture</div>
            <form class="flex flex-col gap-3" @submit.prevent="quickCapture">
                <div v-if="quickError" class="text-sm text-danger" role="alert">{{ quickError }}</div>
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
                <KButton type="submit" variant="primary" data-testid="qc-submit">
                    Capture
                </KButton>
            </form>
        </section>

        <EmergencyPauseDialog
            v-if="emergencyDialogOpen"
            :date="props.date"
            :current-task-id="currentEvent?.task?.id ?? null"
            @confirmed="onEmergencyConfirmed"
            @cancelled="onEmergencyCancelled"
        />

        <BreakModeDialog
            v-if="breakDialogOpen"
            :date="props.date"
            @confirmed="onBreakConfirmed"
            @cancelled="onBreakCancelled"
        />

        <BoostDialog
            v-if="boostDialogOpen && today.breakPeriod"
            :break-period-id="today.breakPeriod.id"
            :start-date="today.breakPeriod.start_date"
            :end-date="today.breakPeriod.end_date"
            @saved="onBoostSaved"
            @cancelled="onBoostCancelled"
        />
    </div>
</template>
