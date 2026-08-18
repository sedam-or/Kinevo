/**
 * Service Worker registration (TASK-050).
 *
 * Registers the shell-cache Service Worker only in browsers that support it
 * and only over secure/HTTPS or localhost contexts. Registration is a
 * progressive enhancement — failures are swallowed so the app still works
 * fully online without a Service Worker.
 */

const SW_URL = '/sw.js';

export function registerServiceWorker(): void {
    if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }
    if (!isSecureContext()) {
        return;
    }
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register(SW_URL)
            .catch(() => {
                // Best-effort: a failed SW registration must not break the app.
            });
    });
}

function isSecureContext(): boolean {
    const loc = typeof window !== 'undefined' ? window.location : null;
    if (loc === null) {
        return false;
    }
    return loc.protocol === 'https:' || loc.hostname === 'localhost' || loc.hostname === '127.0.0.1';
}
