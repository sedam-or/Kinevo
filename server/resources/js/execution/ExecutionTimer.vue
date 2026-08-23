<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useExecutionStore } from './store';
import type { ApiError } from '../api/types';

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
    <div class="border border-gray-300 dark:border-gray-600 rounded-sm p-3" data-testid="execution-timer">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Execution</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ taskTitle ?? 'Timer' }}</div>
            </div>
            <div class="text-right">
                <div class="text-lg font-mono tabular-nums" data-testid="execution-elapsed">{{ formatSeconds(execution.elapsedSeconds) }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400" data-testid="execution-status">{{ statusLabel }}</div>
            </div>
        </div>

        <div v-if="actionError" class="text-sm text-danger" role="alert" data-testid="execution-error">
            {{ actionError.message }}
        </div>

        <div class="flex gap-2 mt-2">
            <button
                v-if="canStart"
                type="button"
                class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                :disabled="busy || execution.loading"
                data-testid="execution-start"
                @click="start"
            >
                Start
            </button>
            <button
                v-if="canPause"
                type="button"
                class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                :disabled="busy"
                data-testid="execution-pause"
                @click="pause"
            >
                Pause
            </button>
            <button
                v-if="canResume"
                type="button"
                class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                :disabled="busy"
                data-testid="execution-resume"
                @click="resume"
            >
                Resume
            </button>
            <button
                v-if="canFinish"
                type="button"
                class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                :disabled="busy"
                :class="{ 'animate-snap': snapping }"
                data-testid="execution-complete"
                @click="complete"
            >
                Complete
            </button>
            <button
                v-if="canFinish"
                type="button"
                class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                :disabled="busy"
                data-testid="execution-abandon"
                @click="abandon"
            >
                Abandon
            </button>
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