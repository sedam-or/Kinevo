import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { adaptiveApi } from './api';
import type { AdaptiveObservation, BurnoutSignalResponse } from './types';
import type { ApiError } from '../api/types';

/** Lightweight adaptive-context check-in store (design.md §23, SRS FR-40). */
export const useAdaptiveStore = defineStore('adaptive', () => {
    const observations = ref<AdaptiveObservation[]>([]);
    const burnout = ref<BurnoutSignalResponse['signal'] | null>(null);
    const loading = ref(false);
    const saving = ref(false);
    const error = ref<ApiError | null>(null);

    const latest = computed(() => observations.value[0] ?? null);

    async function load(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const data = await adaptiveApi.list();
            observations.value = data.observations;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function loadBurnout(): Promise<void> {
        try {
            burnout.value = (await adaptiveApi.burnout()).signal;
        } catch {
            // Burnout signal is advisory; a failure must not break the surface.
        }
    }

    async function checkIn(
        payload: Parameters<typeof adaptiveApi.checkIn>[0],
    ): Promise<AdaptiveObservation | null> {
        saving.value = true;
        error.value = null;
        try {
            const { observation } = await adaptiveApi.checkIn(payload);
            observations.value.unshift(observation);
            return observation;
        } catch (err) {
            error.value = err as ApiError;
            return null;
        } finally {
            saving.value = false;
        }
    }

    function clear(): void {
        observations.value = [];
        burnout.value = null;
        error.value = null;
    }

    return {
        observations,
        burnout,
        latest,
        loading,
        saving,
        error,
        load,
        loadBurnout,
        checkIn,
        clear,
    };
});