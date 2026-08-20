<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useCanvasStore } from './store';
import { CanvasAutosaveController, type CanvasSaveStateChange } from './autosave';
import { HttpCanvasPersistence } from './http-persistence';
import CanvasHost from './CanvasHost.vue';
import CanvasContextPanel from './CanvasContextPanel.vue';
import VisualStateBadge from '../visualstate/VisualStateBadge.vue';
import type { VisualStateValue } from '../visualstate/types';
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

const title = ref('');
const scene = ref<CanvasScene | null>(null);
const readOnly = ref(false);
const theme = ref<CanvasTheme>('auto');
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

onBeforeUnmount(() => {
    if (controller) {
        void controller.flush();
        controller.dispose();
        controller = null;
    }
});

function cycleTheme(): void {
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

function conflictRecover(): void {
    if (!canvas.current) {
        return;
    }
    if (controller) {
        const authoritative = (canvas.document ?? { elements: [], appState: {} }) as unknown as CanvasScene;
        controller.reconcile(authoritative, canvas.documentVersion);
        scene.value = authoritative;
    }
}
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="canvas-workspace">
        <header class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button type="button" class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="canvas-back" @click="emit('back')">← Back</button>
                <input
                    v-model="title"
                    type="text"
                    class="text-xl font-semibold bg-transparent border border-transparent focus:border-gray-300 dark:focus:border-gray-600 rounded-sm px-2 py-1"
                    data-testid="canvas-title-input"
                    @change="saveTitle"
                    @keyup.enter="saveTitle"
                />
            </div>
            <div class="flex items-center gap-3">
                <span data-testid="canvas-save-state"><VisualStateBadge :state="saveStateBadge" /></span>
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
        <div v-if="canvas.error && !canvas.current" class="text-sm text-[#F53003]" role="alert" data-testid="canvas-error">
            {{ canvas.error.message }}
        </div>
        <div v-if="renameError" class="text-sm text-[#F53003]" role="alert" data-testid="canvas-rename-error">{{ renameError }}</div>
        <div v-if="archiveError" class="text-sm text-[#F53003]" role="alert" data-testid="canvas-archive-error">{{ archiveError }}</div>

        <div v-if="canvas.saveState === 'conflict'" class="border border-dashed border-[#F53003] rounded-sm px-4 py-2 text-sm" data-testid="canvas-conflict" role="alert">
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
                <button type="button" class="text-sm border border-[#F53003] text-[#F53003] rounded-sm px-3 py-1" data-testid="canvas-archive-confirm-action" @click="doArchive">Archive</button>
            </div>
        </div>

        <section v-if="canvas.current" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="canvas-context-section">
            <CanvasContextPanel :canvas-id="props.canvasId" />
        </section>
    </div>
</template>