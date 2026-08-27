<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRechargeStore } from './store';
import type { ApiError } from '../api/types';

const props = defineProps<{
    date?: string;
}>();

const emit = defineEmits<{
    (e: 'completed'): void;
}>();

const recharge = useRechargeStore();

const busy = ref(false);
const actionError = ref<ApiError | null>(null);

onMounted(() => {
    void recharge.loadStatus(props.date);
});

onUnmounted(() => {
    recharge.clear();
});

const statusLabel = computed(() => {
    if (recharge.isRunning) {
        return 'Running';
    }
    if (recharge.isPaused) {
        return 'Paused';
    }
    return 'Ready';
});

const canStart = computed(() => recharge.cueAvailable && !recharge.hasActive);
const canPause = computed(() => recharge.isRunning);
const canResume = computed(() => recharge.isPaused);
const canFinish = computed(() => recharge.hasActive);

const ratioLabel = computed(() => {
    if (recharge.productiveMinutesToday + recharge.rechargeMinutesToday === 0) {
        return '';
    }
    const work = Math.round(recharge.workRatio * 100);
    const rest = Math.round(recharge.rechargeRatio * 100);
    return `Work ${work}% · Recharge ${rest}%`;
});

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

async function start(): Promise<void> {
    await run(async () => {
        await recharge.start();
        await recharge.loadStatus(props.date);
    });
}

function pause(): Promise<void> {
    return run(() => recharge.pause());
}

function resume(): Promise<void> {
    return run(() => recharge.resume());
}

function abandon(): Promise<void> {
    return run(async () => {
        await recharge.abandon();
        await recharge.loadStatus(props.date);
    });
}

async function complete(): Promise<void> {
    await run(async () => {
        await recharge.complete();
        emit('completed');
        await recharge.loadStatus(props.date);
    });
}
</script>

<template>
    <div
        v-if="canStart || recharge.hasActive || ratioLabel !== ''"
        class="border border-success/40 rounded-sm p-3"
        data-testid="recharge-timer"
    >
        <div class="flex items-center justify-between">
            <div>
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Recharge</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Recovery · 15 min recommended</div>
                <div v-if="ratioLabel" class="text-xs text-gray-500 dark:text-gray-400" data-testid="recharge-ratio">{{ ratioLabel }}</div>
            </div>
            <div class="text-right">
                <div v-if="recharge.hasActive" class="text-lg font-mono tabular-nums" data-testid="recharge-elapsed">
                    {{ formatSeconds(recharge.elapsedSeconds) }}
                </div>
                <div v-if="recharge.hasActive" class="text-xs text-gray-500 dark:text-gray-400" data-testid="recharge-status">{{ statusLabel }}</div>
                <div v-else-if="recharge.cueAvailable" class="text-sm text-success" data-testid="recharge-cue">Time to recharge</div>
            </div>
        </div>

        <div v-if="actionError" class="text-sm text-danger" role="alert" data-testid="recharge-error">
            {{ actionError.message }}
        </div>

        <div class="flex gap-2 mt-2">
            <button
                v-if="canStart"
                type="button"
                class="border border-success/50 rounded-sm px-3 py-1 text-sm"
                :disabled="busy || recharge.loading"
                data-testid="recharge-start"
                @click="start"
            >
                Start recharge
            </button>
            <button
                v-if="canPause"
                type="button"
                class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                :disabled="busy"
                data-testid="recharge-pause"
                @click="pause"
            >
                Pause
            </button>
            <button
                v-if="canResume"
                type="button"
                class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                :disabled="busy"
                data-testid="recharge-resume"
                @click="resume"
            >
                Resume
            </button>
            <button
                v-if="canFinish"
                type="button"
                class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                :disabled="busy"
                data-testid="recharge-complete"
                @click="complete"
            >
                Complete
            </button>
            <button
                v-if="canFinish"
                type="button"
                class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                :disabled="busy"
                data-testid="recharge-abandon"
                @click="abandon"
            >
                Abandon
            </button>
        </div>
    </div>
</template>