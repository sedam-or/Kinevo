import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from '../../auth/store';
import { useApiStore } from '../../api/store';
import { useDiagnostics, type DiagnosticsSnapshot } from '../useDiagnostics';
import { isOfflineSupported, browserOnline } from '../../offline/diagnostics';

describe('diagnostics helpers (design.md §78)', () => {
    it('reports offline primitives availability', () => {
        expect(typeof isOfflineSupported()).toBe('boolean');
        expect(browserOnline()).toBe(true);
    });
});

describe('useDiagnostics snapshot', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('reflects the current runtime store state', () => {
        const { snapshot } = useDiagnostics();
        const value = snapshot.value;

        expect(value.authStatus).toBe('unknown');
        expect(value.shellView).toBe('today');
        expect(value.syncQueuedCount).toBe(0);
        expect(typeof (value as DiagnosticsSnapshot).apiOnline).toBe('boolean');
    });

    it('tracks auth and API state changes', async () => {
        const { snapshot } = useDiagnostics();
        const auth = useAuthStore();
        const api = useApiStore();

        auth.status = 'authenticated';
        auth.user = { id: 1, name: 'Juan', email: 'juan@example.com' };
        api.setOnline(false);

        expect(snapshot.value.authStatus).toBe('authenticated');
        expect(snapshot.value.authEmail).toBe('juan@example.com');
        expect(snapshot.value.apiOnline).toBe(false);
    });
});