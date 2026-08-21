/**
 * Runtime diagnostics helpers (TASK-R2, design.md §78).
 *
 * Framework-agnostic connectivity checks used by the dev-only diagnostics
 * panel and by the E2E diagnostics probe. Kept free of Pinia/DOM so they are
 * trivially unit-testable and reusable from a non-UI probe.
 */

/** Whether offline primitives the app relies on are present. */
export function isOfflineSupported(): boolean {
    return (
        typeof navigator !== 'undefined' &&
        typeof navigator.onLine === 'boolean' &&
        typeof indexedDB !== 'undefined' &&
        typeof window !== 'undefined' &&
        typeof window.caches !== 'undefined'
    );
}

/** Best-effort current online status, tolerant of non-browser environments. */
export function browserOnline(): boolean {
    return typeof navigator === 'undefined' || navigator.onLine !== false;
}
