<script setup lang="ts">
import { computed } from 'vue';
import { useDiagnostics } from './useDiagnostics';

/**
 * Dev-only runtime diagnostics panel (TASK-R2, design.md §78/§36).
 *
 * Rendered only in development. In production builds `import.meta.env.PROD`
 * is a compile-time constant so this panel is dropped from the bundle and no
 * diagnostics are exposed to real users (design.md §36 "disabled or protected
 * in production"). Visualizes API / auth / offline / shell / sync state.
 */
const { snapshot, refresh } = useDiagnostics();

const isDev = import.meta.env.DEV;

const rows = computed(() => [
    { k: 'App mode', v: snapshot.value.appMode },
    { k: 'API online', v: String(snapshot.value.apiOnline) },
    { k: 'API in-flight', v: String(snapshot.value.apiInFlight) },
    { k: 'API last error', v: snapshot.value.apiLastError ?? '—' },
    { k: 'Auth status', v: snapshot.value.authStatus },
    { k: 'Auth user', v: snapshot.value.authEmail ?? '—' },
    { k: 'View', v: snapshot.value.shellView },
    { k: 'Sync state', v: snapshot.value.syncState },
    { k: 'Sync queued', v: String(snapshot.value.syncQueuedCount) },
    { k: 'Offline supported', v: String(snapshot.value.offlineSupported) },
]);
</script>

<template>
    <div v-if="isDev" class="fixed bottom-2 left-2 z-[800] max-w-xs text-xs border border-gray-700 bg-[#131313] text-gray-100 rounded-sm p-3 shadow-lg" data-testid="runtime-diagnostics">
        <div class="flex items-center justify-between mb-2">
            <strong class="uppercase tracking-wide text-[10px] opacity-70">Runtime diagnostics</strong>
            <button type="button" class="underline opacity-70 hover:opacity-100" data-testid="diagnostics-refresh" @click="refresh">refresh</button>
        </div>
        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
            <template v-for="row in rows" :key="row.k">
                <dt class="opacity-60">{{ row.k }}</dt>
                <dd class="font-mono break-all" data-testid="diagnostics-row">{{ row.v }}</dd>
            </template>
        </dl>
    </div>
</template>
