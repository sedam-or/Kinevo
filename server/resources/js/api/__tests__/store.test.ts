import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useApiStore } from '../store';

beforeEach(() => {
    setActivePinia(createPinia());
});

describe('api store', () => {
    it('tracks in-flight requests and loading state', () => {
        const api = useApiStore();
        expect(api.isLoading).toBe(false);
        api.beginRequest();
        api.beginRequest();
        expect(api.isLoading).toBe(true);
        expect(api.inFlight).toBe(2);
        api.endRequest();
        expect(api.inFlight).toBe(1);
        api.endRequest();
        expect(api.isLoading).toBe(false);
    });

    it('records the last error and clears it', () => {
        const api = useApiStore();
        api.endRequest({ code: 'CONFLICT', status: 409, message: 'conflict', retryable: false });
        expect(api.lastError?.code).toBe('CONFLICT');
        api.clearError();
        expect(api.lastError).toBeNull();
    });

    it('tracks online/offline connectivity', () => {
        const api = useApiStore();
        api.setOnline(true);
        expect(api.online).toBe(true);
        api.setOnline(false);
        expect(api.online).toBe(false);
    });

    it('tracks the offline queue count', () => {
        const api = useApiStore();
        api.setOfflineQueueCount(3);
        expect(api.offlineQueueCount).toBe(3);
    });
});
