import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { executionApi } from './api';
import type { ExecutionSession } from './types';
import type { ApiError } from '../api/types';

export const useExecutionStore = defineStore('execution', () => {
    const session = ref<ExecutionSession | null>(null);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);
    const now = ref(Date.now());

    let ticker: ReturnType<typeof setInterval> | null = null;

    const isRunning = computed(() => session.value?.status === 'running');
    const isPaused = computed(() => session.value?.status === 'paused');
    const hasActive = computed(() => isRunning.value || isPaused.value);

    // FR-05: elapsed time is derived from persisted timestamps — accumulated
    // seconds plus the current running segment, never a client-only model.
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

    function apply(updated: ExecutionSession): void {
        session.value = updated;
        if (updated.status === 'running') {
            now.value = Date.now();
            startTicker();
        } else {
            stopTicker();
        }
    }

    async function loadActive(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const { execution } = await executionApi.active();
            session.value = execution;
            if (execution?.status === 'running') {
                now.value = Date.now();
                startTicker();
            } else {
                stopTicker();
            }
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function start(taskId: number): Promise<ExecutionSession> {
        error.value = null;
        const { execution } = await executionApi.start(taskId);
        apply(execution);
        return execution;
    }

    async function pause(): Promise<ExecutionSession> {
        if (!session.value) {
            throw new Error('No active execution session.');
        }
        error.value = null;
        const { execution } = await executionApi.pause(session.value.id);
        apply(execution);
        return execution;
    }

    async function resume(): Promise<ExecutionSession> {
        if (!session.value) {
            throw new Error('No active execution session.');
        }
        error.value = null;
        const { execution } = await executionApi.resume(session.value.id);
        apply(execution);
        return execution;
    }

    async function complete(): Promise<ExecutionSession> {
        if (!session.value) {
            throw new Error('No active execution session.');
        }
        error.value = null;
        const { execution } = await executionApi.complete(session.value.id);
        apply(execution);
        return execution;
    }

    async function abandon(): Promise<ExecutionSession> {
        if (!session.value) {
            throw new Error('No active execution session.');
        }
        error.value = null;
        const { execution } = await executionApi.abandon(session.value.id);
        apply(execution);
        return execution;
    }

    function clear(): void {
        stopTicker();
        session.value = null;
        error.value = null;
        now.value = Date.now();
    }

    return {
        session,
        loading,
        error,
        now,
        isRunning,
        isPaused,
        hasActive,
        elapsedSeconds,
        loadActive,
        start,
        pause,
        resume,
        complete,
        abandon,
        clear,
    };
});