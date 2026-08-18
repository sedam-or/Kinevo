import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { registerServiceWorker } from './offline/register-sw';

// Mount the Vue application only when a host element exists. The default
// Laravel welcome page has no #app host, so this is a no-op there.
const host = document.getElementById('app');

if (host) {
    const app = createApp({});
    app.use(createPinia());
    app.mount(host);
}

// Register the Service Worker for app-shell caching (offline-sync.md
// §Service Worker). Guarded so it only runs in browsers with SW support and
// never throws in tests or unsupported environments.
registerServiceWorker();

export {};
