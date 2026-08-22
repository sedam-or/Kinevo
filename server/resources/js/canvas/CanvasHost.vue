<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, toRaw, watch } from 'vue';
import { ExcalidrawCanvasAdapter } from './ExcalidrawCanvasAdapter';
import type { CanvasAdapter, CanvasScene, CanvasTheme } from './types';

const props = withDefaults(
    defineProps<{
        scene?: CanvasScene | null;
        readOnly?: boolean;
        theme?: CanvasTheme;
        adapterFactory?: () => CanvasAdapter;
    }>(),
    {
        scene: null,
        readOnly: false,
        theme: 'auto',
        adapterFactory: undefined,
    },
);

const emit = defineEmits<{
    (e: 'change', scene: CanvasScene): void;
    (e: 'ready', adapter: CanvasAdapter): void;
}>();

const host = ref<HTMLElement | null>(null);
let adapter: CanvasAdapter | null = null;
// The exact scene object this host last emitted upstream. When that same
// reference comes back through props.scene (workspace mirrors changes), we
// must NOT load it into the engine again — doing so re-enters Excalidraw's
// onChange and creates an infinite change→prop→load loop that starves the
// autosave debounce. Only foreign scenes (initial, server reconcile) load.
let lastEmittedScene: CanvasScene | null = null;

/** Editor entry state (design.md §34.2): loading → ready, or a failure surface. */
const editorState = ref<'loading' | 'ready' | 'error'>('loading');
const mountError = ref<string | null>(null);

function createAdapter(): CanvasAdapter {
    if (props.adapterFactory) {
        return props.adapterFactory();
    }
    return new ExcalidrawCanvasAdapter();
}

function bootAdapter(readOnlyOnly = false): void {
    if (host.value === null) {
        return;
    }
    editorState.value = 'loading';
    mountError.value = null;
    try {
        adapter = createAdapter();
        adapter.subscribe((change) => {
            lastEmittedScene = change.scene;
            emit('change', lastEmittedScene);
        });
        adapter.mount(host.value);
        adapter.load(props.scene ?? null);
        adapter.setReadOnly(readOnlyOnly || props.readOnly);
        adapter.setTheme(props.theme);
    } catch (err) {
        // Never leave the page blank (design.md §34.2).
        adapter?.destroy();
        adapter = null;
        mountError.value = err instanceof Error ? err.message : 'Canvas editor failed to initialize.';
        editorState.value = 'error';
        return;
    }
    editorState.value = 'ready';
    // Dev/e2e-only seam: lets browser tests drive the REAL adapter boundary
    // (§82) — headless runners cannot deliver trusted pointer events into
    // Excalidraw. Compiled in only via `KINEVO_E2E_SEAM=1` builds
    // (__KINEVO_E2E_SEAM__ define, see vite.config.ts); plain production
    // builds dead-code-eliminate this block entirely. Never used by app code.
    if (__KINEVO_E2E_SEAM__) {
        (window as unknown as { __kinevoCanvasAdapter?: CanvasAdapter }).__kinevoCanvasAdapter =
            adapter;
    }
    emit('ready', adapter);
}

function retry(): void {
    bootAdapter();
}

function openReadOnly(): void {
    // Reboot but lock the editor to read-only so the raw data is safe to
    // inspect (design.md §34.2 "Open read-only data").
    bootAdapter(true);
}

onMounted(() => {
    bootAdapter();
});

watch(
    () => props.scene,
    (scene) => {
        // Skip echoes of our own changes (see lastEmittedScene above). Vue
        // refs wrap assigned plain objects in reactive proxies, so the value
        // coming back through props may be a proxy of the exact object we
        // emitted — compare raw identities, never proxy vs raw.
        if (
            editorState.value === 'ready' &&
            toRaw(scene ?? ({} as CanvasScene)) !== toRaw(lastEmittedScene ?? ({} as CanvasScene))
        ) {
            adapter?.load(scene ?? null);
        }
    },
);

watch(
    () => props.readOnly,
    (enabled) => {
        if (editorState.value === 'ready') {
            adapter?.setReadOnly(enabled);
        }
    },
);

watch(
    () => props.theme,
    (theme) => {
        if (editorState.value === 'ready') {
            adapter?.setTheme(theme);
        }
    },
);

onBeforeUnmount(() => {
    adapter?.destroy();
    adapter = null;
    if (__KINEVO_E2E_SEAM__) {
        delete (window as unknown as { __kinevoCanvasAdapter?: unknown }).__kinevoCanvasAdapter;
    }
});
</script>

<template>
    <div class="relative h-full min-h-24">
        <!-- The editor container is always mounted so boot can target it (§34.2);
             a definite height is REQUIRED: Excalidraw measures its container and
             a 0/auto height resolves to the max-canvas sentinel, which exceeds
             browser canvas limits and crashes the tab (TASK-R4 browser finding). -->
        <div
            ref="host"
            class="kinevo-canvas-host h-full"
            data-testid="canvas-host"
            :class="{ 'invisible': editorState !== 'ready' }"
        ></div>

        <!-- Loading editor… entry state (design.md §34.2) -->
        <div v-if="editorState === 'loading'" class="absolute inset-0 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400" data-testid="canvas-editor-loading">
            Loading editor…
        </div>

        <!-- Failure surface — never a blank page (design.md §34.2/§35) -->
        <div v-else-if="editorState === 'error'" class="absolute inset-0 border border-dashed border-danger rounded-sm px-4 py-4 text-sm" data-testid="canvas-editor-error" role="alert" aria-live="polite">
            <p class="font-medium">Canvas editor failed to initialize.</p>
            <p v-if="mountError" class="text-gray-600 dark:text-gray-400">{{ mountError }}</p>
            <p class="mt-1 text-gray-600 dark:text-gray-400">Your saved canvas data is still safe.</p>
            <div class="flex gap-2 mt-3">
                <button type="button" class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1" data-testid="canvas-editor-retry" @click="retry">Retry</button>
                <button type="button" class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1" data-testid="canvas-editor-readonly" @click="openReadOnly">Open read-only</button>
            </div>
        </div>
    </div>
</template>