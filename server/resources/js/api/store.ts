import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type { ApiError } from './types';

export const useApiStore = defineStore('api', () => {
    const inFlight = ref(0);
    const lastError = ref<ApiError | null>(null);
    const online = ref(typeof navigator === 'undefined' || navigator.onLine !== false);
    const offlineQueueCount = ref(0);

    const isLoading = computed(() => inFlight.value > 0);

    function beginRequest(): void {
        inFlight.value += 1;
    }

    function endRequest(error: ApiError | null = null): void {
        inFlight.value = Math.max(0, inFlight.value - 1);
        if (error !== null) {
            lastError.value = error;
        }
    }

    function setOnline(isOnline: boolean): void {
        online.value = isOnline;
    }

    function clearError(): void {
        lastError.value = null;
    }

    function setOfflineQueueCount(count: number): void {
        offlineQueueCount.value = count;
    }

    return {
        inFlight,
        lastError,
        online,
        offlineQueueCount,
        isLoading,
        beginRequest,
        endRequest,
        setOnline,
        clearError,
        setOfflineQueueCount,
    };
});
