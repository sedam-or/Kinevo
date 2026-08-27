<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRechargeStore } from './store';
import type { ApiError } from '../api/types';
import KButton from '../components/KButton.vue';

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
    <!-- Embedded L5 metadata strip of the NOW hero: same structure, paddings
         and typography as ExecutionTimer so they read as siblings. -->
    <div
        v-if="canStart || recharge.hasActive || ratioLabel !== ''"
        class="surface-metadata border-t border-border pt-3"
        data-testid="recharge-timer"
    >
        <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
            <div class="min-w-0">
                <div class="text-xs font-semibold text-text-muted">Recharge</div>
                <div class="mt-0.5 truncate text-sm text-text-muted">Recovery · 15 min recommended</div>
                <div v-if="ratioLabel" class="text-xs text-text-muted tabular-nums" data-testid="recharge-ratio">{{ ratioLabel }}</div>
            </div>
            <div class="text-right">
                <template v-if="recharge.hasActive">
                    <div class="font-mono text-xl leading-none tabular-nums" data-testid="recharge-elapsed">
                        {{ formatSeconds(recharge.elapsedSeconds) }}
                    </div>
                    <div class="mt-1 text-xs font-semibold text-text-muted" data-testid="recharge-status">{{ statusLabel }}</div>
                </template>
                <div v-else-if="recharge.cueAvailable" class="text-sm font-medium text-success" data-testid="recharge-cue">Time to recharge</div>
            </div>
        </div>

        <div v-if="actionError" class="mt-2 text-sm text-danger" role="alert" data-testid="recharge-error">
            {{ actionError.message }}
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <KButton
                v-if="canStart"
                variant="secondary"
                :disabled="busy || recharge.loading"
                data-testid="recharge-start"
                @click="start"
            >
                Start recharge
            </KButton>
            <KButton
                v-if="canPause"
                variant="secondary"
                :disabled="busy"
                data-testid="recharge-pause"
                @click="pause"
            >
                Pause
            </KButton>
            <KButton
                v-if="canResume"
                variant="secondary"
                :disabled="busy"
                data-testid="recharge-resume"
                @click="resume"
            >
                Resume
            </KButton>
            <KButton
                v-if="canFinish"
                variant="secondary"
                :disabled="busy"
                data-testid="recharge-complete"
                @click="complete"
            >
                Complete
            </KButton>
            <KButton
                v-if="canFinish"
                variant="ghost"
                :disabled="busy"
                data-testid="recharge-abandon"
                @click="abandon"
            >
                Abandon
            </KButton>
        </div>
    </div>
</template>
