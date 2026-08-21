import { computed, ref, type ComputedRef } from 'vue';
import { useAuthStore } from '../auth/store';
import { useShellStore } from '../shell/store';
import { useApiStore } from '../api/store';
import { isOfflineSupported } from '../offline/diagnostics';

/** Stable machine-readable snapshot of the runtime diagnostic bridges. */
export interface DiagnosticsSnapshot {
    appMode: string;
    apiOnline: boolean;
    apiInFlight: number;
    apiLastError: string | null;
    authStatus: string;
    authEmail: string | null;
    shellView: string;
    syncState: string;
    syncQueuedCount: number;
    offlineSupported: boolean;
}

/**
 * Build a runtime diagnostics snapshot from the real Pinia stores.
 *
 * Dev-only helper for design.md §78/§36: lets a developer inspect API, auth,
 * offline, and shell state without poking the browser console. The composable
 * is also useful for tests (inject mounts/probes lazily).
 *
 * Canvas/editor probe values are intentionally reported as availability flags
 * rather than deep introspection: the domain shells own those concerns and
 * deeper per-boundary checks live in TASK-R4 (canvas-hardening).
 */
export function useDiagnostics(): {
    snapshot: ComputedRef<DiagnosticsSnapshot>;
    refresh: () => void;
} {
    const auth = useAuthStore();
    const shell = useShellStore();
    const api = useApiStore();

    const nonce = ref(0);

    return {
        snapshot: computed<DiagnosticsSnapshot>(() => {
            void nonce.value;
            return {
                appMode: import.meta.env.MODE ?? 'production',
                apiOnline: api.online,
                apiInFlight: api.inFlight,
                apiLastError: api.lastError?.message ?? null,
                authStatus: auth.status,
                authEmail: auth.user?.email ?? null,
                shellView: shell.activeView,
                syncState: shell.syncState,
                syncQueuedCount: shell.syncQueuedCount,
                offlineSupported: isOfflineSupported(),
            };
        }),
        refresh(): void {
            nonce.value += 1;
        },
    };
}
