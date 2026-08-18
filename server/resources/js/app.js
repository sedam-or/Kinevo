import { createApp } from 'vue';
import { createPinia } from 'pinia';

// Mount the Vue application only when a host element exists. The default
// Laravel welcome page has no #app host, so this is a no-op there.
const host = document.getElementById('app');

if (host) {
    const app = createApp({});
    app.use(createPinia());
    app.mount(host);
}

export {};