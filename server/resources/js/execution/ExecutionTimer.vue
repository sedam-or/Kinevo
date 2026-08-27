<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useExecutionStore } from './store';
import type { ApiError } from '../api/types';
import KButton from '../components/KButton.vue';

const props = defineProps<{
    taskId: number;
    taskTitle?: string;
}>();

const emit = defineEmits<{
    (e: 'completed'): void;
}>();

const execution = useExecutionStore();

const busy = ref(false);
const snapping = ref(false);
const actionError = ref<ApiError | null>(null);

onMounted(() => {
    void execution.loadActive();
});

onUnmounted(() => {
    execution.clear();
});

const statusLabel = computed(() => {
    if (execution.isRunning) {
        return 'Running';
    }
    if (execution.isPaused) {
        return 'Paused';
    }
    return 'Ready';
});

const canStart = computed(() => !execution.hasActive);
const canPause = computed(() => execution.isRunning);
const canResume = computed(() => execution.isPaused);
const canFinish = computed(() => execution.hasActive);

function formatSeconds(total: number): string {
    const seconds = Math.max(0, total);
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    const mm = String(m).padStart(2, '0');
    const ss = String(s).padStart(2, '0');
    return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
}

async function run(action: () => Promise<unknown>): Promise<void> {
    actionError.value = null;
    busy.value = true;
    try {
        await action();
    } catch (err) {
        actionError.value = err as ApiError;
    } finally {
        busy.value = false;
    }
}

function start(): Promise<void> {
    return run(() => execution.start(props.taskId));
}

function pause(): Promise<void> {
    return run(() => execution.pause());
}

function resume(): Promise<void> {
    return run(() => execution.resume());
}

function abandon(): Promise<void> {
    return run(() => execution.abandon());
}

async function complete(): Promise<void> {
    // Checkbox snap feedback (TASK-P17-011): press registers instantly,
    // the request resolves in place.
    snapping.value = true;
    try {
        await run(async () => {
            await execution.complete();
            emit('completed');
        });
    } finally {
        snapping.value = false;
    }
}
</script>

<template>
    <!-- Embedded L5 metadata strip of the NOW hero: rides directly on the
         hero surface, separated by a thin border-t (never boxed). Structure
         mirrors RechargeTimer exactly so both read as siblings. -->
    <div class="surface-metadata border-t border-border pt-3" data-testid="execution-timer">
        <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
            <div class="min-w-0">
                <div class="text-xs font-semibold text-text-muted">Execution</div>
                <div class="mt-0.5 truncate text-sm text-text-muted">{{ taskTitle ?? 'Timer' }}</div>
            </div>
            <div class="text-right">
                <div class="font-mono text-xl leading-none tabular-nums" data-testid="execution-elapsed">{{ formatSeconds(execution.elapsedSeconds) }}</div>
                <div class="mt-1 text-xs font-semibold text-text-muted" data-testid="execution-status">{{ statusLabel }}</div>
            </div>
        </div>

        <div v-if="actionError" class="mt-2 text-sm text-danger" role="alert" data-testid="execution-error">
            {{ actionError.message }}
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <KButton
                v-if="canStart"
                variant="secondary"
                :disabled="busy || execution.loading"
                data-testid="execution-start"
                @click="start"
            >
                Start
            </KButton>
            <KButton
                v-if="canPause"
                variant="secondary"
                :disabled="busy"
                data-testid="execution-pause"
                @click="pause"
            >
                Pause
            </KButton>
            <KButton
                v-if="canResume"
                variant="secondary"
                :disabled="busy"
                data-testid="execution-resume"
                @click="resume"
            >
                Resume
            </KButton>
            <KButton
                v-if="canFinish"
                variant="secondary"
                :disabled="busy"
                :class="{ 'animate-snap': snapping }"
                data-testid="execution-complete"
                @click="complete"
            >
                Complete
            </KButton>
            <KButton
                v-if="canFinish"
                variant="ghost"
                :disabled="busy"
                data-testid="execution-abandon"
                @click="abandon"
            >
                Abandon
            </KButton>
        </div>
    </div>
</template>

<style scoped>
@keyframes snap {
    0% { transform: scale(1); }
    50% { transform: scale(1.12) rotate(-4deg); }
    100% { transform: scale(1); }
}
.animate-snap {
    animation: snap 180ms ease-in-out;
}
@media (prefers-reduced-motion: reduce) {
    .animate-snap {
        animation: none;
    }
}
</style>
