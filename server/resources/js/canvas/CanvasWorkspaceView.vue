<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useCanvasStore } from './store';
import { CanvasAutosaveController, type CanvasSaveStateChange } from './autosave';
import { HttpCanvasPersistence } from './http-persistence';
import CanvasHost from './CanvasHost.vue';
import CanvasContextPanel from './CanvasContextPanel.vue';
import VisualStateBadge from '../visualstate/VisualStateBadge.vue';
import type { VisualStateValue } from '../visualstate/types';
import { useShellStore } from '../shell/store';
import { resolvedTheme } from '../shell/theme';
import type { CanvasAdapter, CanvasScene, CanvasTheme } from './types';

const props = withDefaults(
    defineProps<{
        canvasId: number;
        adapterFactory?: () => CanvasAdapter;
    }>(),
    {
        adapterFactory: undefined,
    },
);

const emit = defineEmits<{
    (e: 'back'): void;
}>();

const canvas = useCanvasStore();
// TASK-P17-013: the canvas follows the app theme by default and keeps
// following it until the user overrides it with the canvas-local toggle.
const shell = useShellStore();
const themeFollowsApp = ref(true);

const title = ref('');
const scene = ref<CanvasScene | null>(null);
const readOnly = ref(false);
const theme = ref<CanvasTheme>(resolvedTheme(shell.theme));
const saveStateBadge = ref<VisualStateValue>('saved');
const confirmArchive = ref(false);
const renameError = ref<string | null>(null);
const archiveError = ref<string | null>(null);

let controller: CanvasAutosaveController | null = null;
let adapter: CanvasAdapter | null = null;

const SAVE_DELAY_MS = 800;

function toVisualState(state: string): VisualStateValue {
    const map: Record<string, VisualStateValue> = {
        saved: 'saved',
        saving: 'syncing',
        dirty: 'saved',
        error: 'failed',
        offline: 'offline',
        conflict: 'conflict',
        failed: 'failed',
    };
    return map[state] ?? 'saved';
}

function onSaveStateChange(change: CanvasSaveStateChange): void {
    saveStateBadge.value = toVisualState(change.state);
    if (change.state === 'conflict') {
        canvas.setSaveState('conflict');
    } else if (change.state === 'offline') {
        canvas.setSaveState('offline');
    } else if (change.state === 'failed') {
        canvas.setSaveState('error');
    } else if (change.state === 'saved') {
        canvas.recordSaved(
            (scene.value ?? { elements: [], appState: {} }) as unknown as Record<string, unknown>,
            change.version ?? canvas.documentVersion,
        );
    }
}

onMounted(async () => {
    await canvas.open(props.canvasId);
    if (canvas.current) {
        title.value = canvas.current.title;
        scene.value = canvas.document
            ? (canvas.document as unknown as CanvasScene)
            : { elements: [], appState: {} };
    }
});

function onCanvasReady(adapterInstance: CanvasAdapter): void {
    adapter = adapterInstance;
    controller = new CanvasAutosaveController(
        adapter,
        new HttpCanvasPersistence(),
        props.canvasId,
        canvas.documentVersion,
        SAVE_DELAY_MS,
    );
    controller.subscribe(onSaveStateChange);
}

function onSceneChange(nextScene: CanvasScene): void {
    scene.value = nextScene;
}

watch(readOnly, (enabled) => {
    adapter?.setReadOnly(enabled);
});

watch(theme, (next) => {
    adapter?.setTheme(next);
});
watch(
    () => shell.theme,
    () => {
        if (themeFollowsApp.value) {
            theme.value = resolvedTheme(shell.theme);
        }
    },
);

onBeforeUnmount(() => {
    if (controller) {
        void controller.flush();
        controller.dispose();
        controller = null;
    }
});

function cycleTheme(): void {
    // A deliberate canvas-local choice detaches from the app theme.
    themeFollowsApp.value = false;
    const order: CanvasTheme[] = ['auto', 'light', 'dark'];
    theme.value = order[(order.indexOf(theme.value) + 1) % order.length];
}

async function saveTitle(): Promise<void> {
    renameError.value = null;
    if (title.value.trim() === '' || !canvas.current || title.value.trim() === canvas.current.title) {
        return;
    }
    const ok = await canvas.rename(title.value.trim());
    if (!ok) {
        renameError.value = canvas.error?.message ?? 'Could not rename canvas.';
        if (canvas.current) {
            title.value = canvas.current.title;
        }
    }
}

async function doArchive(): Promise<void> {
    archiveError.value = null;
    const ok = await canvas.archive();
    if (ok) {
        emit('back');
        return;
    }
    archiveError.value = canvas.error?.message ?? 'Could not archive canvas.';
    confirmArchive.value = false;
}

async function conflictRecover(): Promise<void> {
    if (!canvas.current || !controller) {
        return;
    }
    // §34.5: "Reload server copy" must adopt the AUTHORITATIVE server state —
    // never the stale in-memory document that lost the version race.
    await canvas.open(props.canvasId);
    if (!canvas.current || !controller) {
        return;
    }
    const authoritative = (canvas.document ?? { elements: [], appState: {} }) as unknown as CanvasScene;
    controller.reconcile(authoritative, canvas.documentVersion);
    scene.value = authoritative;
}
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="canvas-workspace">
        <header class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
            <div class="flex items-center gap-2 min-w-0">
                <button type="button" class="shrink-0 text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="canvas-back" @click="emit('back')">← Back</button>
                <input
                    v-model="title"
                    type="text"
                    class="min-w-0 max-w-full text-xl font-semibold bg-transparent border border-transparent focus:border-gray-300 dark:focus:border-gray-600 rounded-sm px-2 py-1"
                    data-testid="canvas-title-input"
                    @change="saveTitle"
                    @keyup.enter="saveTitle"
                />
            </div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <!-- role=status + aria-live: save-state transitions (§34.4) are
                     announced politely; the badge label is the announced text. -->
                <span data-testid="canvas-save-state" role="status" aria-live="polite"><VisualStateBadge :state="saveStateBadge" /></span>
                <button type="button" class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="canvas-theme-toggle" @click="cycleTheme">
                    Theme: {{ theme }}
                </button>
                <label class="flex items-center gap-1 text-sm" data-testid="canvas-readonly-toggle">
                    <input v-model="readOnly" type="checkbox" class="accent-current" />
                    Read-only
                </label>
                <button
                    type="button"
                    class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1"
                    data-testid="canvas-archive"
                    @click="confirmArchive = !confirmArchive"
                >
                    Archive
                </button>
            </div>
        </header>

        <div v-if="canvas.loading" class="text-sm text-gray-500" data-testid="canvas-loading">Loading…</div>
        <div v-if="canvas.error && !canvas.current" class="text-sm text-danger" role="alert" data-testid="canvas-error">
            {{ canvas.error.message }}
        </div>
        <div v-if="renameError" class="text-sm text-danger" role="alert" data-testid="canvas-rename-error">{{ renameError }}</div>
        <div v-if="archiveError" class="text-sm text-danger" role="alert" data-testid="canvas-archive-error">{{ archiveError }}</div>

        <div v-if="canvas.saveState === 'conflict'" class="border border-dashed border-danger rounded-sm px-4 py-2 text-sm" data-testid="canvas-conflict" role="alert">
            This canvas was changed elsewhere.
            <button type="button" class="ml-2 underline" data-testid="canvas-conflict-reload" @click="conflictRecover">Reload server copy</button>
        </div>

        <section v-if="canvas.current" class="border border-gray-300 dark:border-gray-600 rounded-sm p-2" style="height: 60vh" data-testid="canvas-surface">
            <CanvasHost
                :scene="scene"
                :read-only="readOnly"
                :theme="theme"
                :adapter-factory="props.adapterFactory"
                @ready="onCanvasReady"
                @change="onSceneChange"
            />
        </section>

        <div v-if="confirmArchive" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="canvas-archive-confirm">
            <p class="text-sm mb-2">Archive this canvas? It will be hidden from your list.</p>
            <div class="flex gap-2">
                <button type="button" class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1" data-testid="canvas-archive-cancel" @click="confirmArchive = false">Cancel</button>
                <button type="button" class="text-sm border border-danger text-danger rounded-sm px-3 py-1" data-testid="canvas-archive-confirm-action" @click="doArchive">Archive</button>
            </div>
        </div>

        <section v-if="canvas.current" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="canvas-context-section">
            <CanvasContextPanel :canvas-id="props.canvasId" />
        </section>
    </div>
</template>