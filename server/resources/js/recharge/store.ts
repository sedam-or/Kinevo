import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { rechargeApi } from './api';
import type { RechargeSession, RechargeStatusResponse } from './types';
import type { ApiError } from '../api/types';

export const useRechargeStore = defineStore('recharge', () => {
    const session = ref<RechargeSession | null>(null);
    const cueAvailable = ref(false);
    const completedFocusToday = ref(0);
    const dueRecharges = ref(0);
    const completedRechargesToday = ref(0);
    const rechargeMinutesToday = ref(0);
    const productiveMinutesToday = ref(0);
    const workRatio = ref(0);
    const rechargeRatio = ref(0);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);
    const now = ref(Date.now());

    let ticker: ReturnType<typeof setInterval> | null = null;

    const isRunning = computed(() => session.value?.status === 'running');
    const isPaused = computed(() => session.value?.status === 'paused');
    const hasActive = computed(() => isRunning.value || isPaused.value);

    // FR-05: recharge elapsed time is derived from persisted timestamps.
    const elapsedSeconds = computed(() => {
        const s = session.value;
        if (!s) {
            return 0;
        }
        if (s.status === 'running' && s.last_resumed_at) {
            const segment = Math.floor((now.value - new Date(s.last_resumed_at).getTime()) / 1000);
            return s.accumulated_seconds + Math.max(0, segment);
        }
        return s.accumulated_seconds;
    });

    function startTicker(): void {
        if (ticker !== null) {
            return;
        }
        ticker = setInterval(() => {
            now.value = Date.now();
        }, 1000);
    }

    function stopTicker(): void {
        if (ticker !== null) {
            clearInterval(ticker);
            ticker = null;
        }
    }

    function apply(status: RechargeStatusResponse): void {
        session.value = status.recharge;
        cueAvailable.value = status.cue_available;
        completedFocusToday.value = status.completed_focus_today;
        dueRecharges.value = status.due_recharges;
        completedRechargesToday.value = status.completed_recharges_today;
        rechargeMinutesToday.value = status.recharge_minutes_today;
        productiveMinutesToday.value = status.productive_minutes_today;
        workRatio.value = status.work_ratio;
        rechargeRatio.value = status.recharge_ratio;

        if (session.value?.status === 'running') {
            now.value = Date.now();
            startTicker();
        } else {
            stopTicker();
        }
    }

    async function loadStatus(date?: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const status = await rechargeApi.status(date);
            apply(status);
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function start(): Promise<RechargeSession> {
        error.value = null;
        const { recharge } = await rechargeApi.start();
        const status = await rechargeApi.status();
        apply(status);
        return recharge;
    }

    async function pause(): Promise<RechargeSession> {
        if (!session.value) {
            throw new Error('No active recharge session.');
        }
        error.value = null;
        const { recharge } = await rechargeApi.pause(session.value.id);
        session.value = recharge;
        stopTicker();
        return recharge;
    }

    async function resume(): Promise<RechargeSession> {
        if (!session.value) {
            throw new Error('No active recharge session.');
        }
        error.value = null;
        const { recharge } = await rechargeApi.resume(session.value.id);
        session.value = recharge;
        if (recharge.status === 'running') {
            now.value = Date.now();
            startTicker();
        }
        return recharge;
    }

    async function complete(): Promise<RechargeSession> {
        if (!session.value) {
            throw new Error('No active recharge session.');
        }
        error.value = null;
        const { recharge } = await rechargeApi.complete(session.value.id);
        session.value = recharge;
        stopTicker();
        return recharge;
    }

    async function abandon(): Promise<RechargeSession> {
        if (!session.value) {
            throw new Error('No active recharge session.');
        }
        error.value = null;
        const { recharge } = await rechargeApi.abandon(session.value.id);
        session.value = recharge;
        stopTicker();
        return recharge;
    }

    function clear(): void {
        stopTicker();
        session.value = null;
        cueAvailable.value = false;
        error.value = null;
        now.value = Date.now();
    }

    return {
        session,
        cueAvailable,
        completedFocusToday,
        dueRecharges,
        completedRechargesToday,
        rechargeMinutesToday,
        productiveMinutesToday,
        workRatio,
        rechargeRatio,
        loading,
        error,
        now,
        isRunning,
        isPaused,
        hasActive,
        elapsedSeconds,
        loadStatus,
        start,
        pause,
        resume,
        complete,
        abandon,
        clear,
    };
});