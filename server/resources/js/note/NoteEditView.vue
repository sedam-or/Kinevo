<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useNoteStore } from './store';
import EditorHost from '../editor/EditorHost.vue';
import type { EditorAdapter, EditorChange, EditorDocument } from '../editor/types';
import VisualStateBadge from '../visualstate/VisualStateBadge.vue';
import type { VisualStateValue } from '../visualstate/types';
import LinkManager from '../knowledge/LinkManager.vue';
import KButton from '../components/KButton.vue';

const props = withDefaults(
    defineProps<{
        noteId: number;
        adapterFactory?: (element: HTMLElement) => EditorAdapter;
    }>(),
    {
        adapterFactory: undefined,
    },
);

const emit = defineEmits<{
    (e: 'back'): void;
}>();

const notes = useNoteStore();

const title = ref('');
const editorDocument = ref<EditorDocument | null>(null);

let adapter: EditorAdapter | null = null;
let saveTimer: ReturnType<typeof setTimeout> | null = null;
let dirty = false;
let pendingPlainText = '';
let pendingMarkdown = '';

const SAVE_DELAY_MS = 600;

onMounted(async () => {
    await notes.load(props.noteId);
    if (notes.current) {
        title.value = notes.current.title;
        editorDocument.value = notes.current.document_json as EditorDocument | null;
    }
});

watch(title, () => {
    dirty = true;
    scheduleSave();
});

function onEditorReady(editor: EditorAdapter): void {
    adapter = editor;
}

function onEditorChange(change: EditorChange): void {
    pendingPlainText = change.derived.plainText;
    pendingMarkdown = change.derived.markdown;
    dirty = true;
    scheduleSave();
}

onBeforeUnmount(() => {
    if (saveTimer !== null) {
        clearTimeout(saveTimer);
    }
    if (dirty && notes.current && adapter) {
        void persist();
    }
});

function scheduleSave(): void {
    if (saveTimer !== null) {
        clearTimeout(saveTimer);
    }
    saveTimer = setTimeout(() => {
        void flush();
    }, SAVE_DELAY_MS);
}

async function flush(): Promise<void> {
    if (saveTimer !== null) {
        clearTimeout(saveTimer);
        saveTimer = null;
    }
    if (!dirty || !notes.current) {
        return;
    }
    await persist();
}

async function persist(): Promise<void> {
    if (!notes.current) {
        return;
    }
    dirty = false;

    let documentJson: Record<string, unknown> | null = null;
    let plainText = pendingPlainText;
    let markdown = pendingMarkdown;

    if (adapter) {
        const snapshot = adapter.save(notes.current.version);
        documentJson = snapshot.document as unknown as Record<string, unknown>;
        plainText = snapshot.derived.plainText;
        markdown = snapshot.derived.markdown;
    }

    await notes.save({
        title: title.value,
        document_json: documentJson,
        plain_text_cache: plainText,
        markdown_cache: markdown,
    });
}

const saveStateBadge = ref<VisualStateValue>('saved');

function toVisualState(state: string): VisualStateValue {
    const map: Record<string, VisualStateValue> = {
        saved: 'saved',
        saving: 'syncing',
        error: 'failed',
        offline: 'offline',
        conflict: 'conflict',
    };
    return map[state] ?? 'saved';
}

watch(
    () => notes.saveState,
    (state) => {
        saveStateBadge.value = toVisualState(state);
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="note-detail">
        <header class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <KButton variant="ghost" data-testid="note-back" @click="emit('back')">← Back</KButton>
                <input
                    v-model="title"
                    type="text"
                    class="text-xl font-semibold bg-transparent border border-transparent focus:border-gray-300 dark:focus:border-gray-600 rounded-sm px-2 py-1"
                    data-testid="note-title-input"
                />
            </div>
            <span data-testid="note-save-state"><VisualStateBadge :state="saveStateBadge" /></span>
        </header>

        <div v-if="notes.loading" class="text-sm text-gray-500" data-testid="note-detail-loading">Loading…</div>
        <div v-if="notes.error" class="text-sm text-[#F53003]" role="alert" data-testid="note-detail-error">
            {{ notes.error.message }}
            <span v-if="notes.saveState === 'conflict'"> — this note was changed elsewhere; reload to reconcile.</span>
        </div>

        <!-- Edit content (Tiptap via the replaceable EditorAdapter) -->
        <section v-if="notes.current" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="note-editor">
            <EditorHost
                :document="editorDocument"
                :read-only="false"
                :adapter-factory="props.adapterFactory"
                @ready="onEditorReady"
                @change="onEditorChange"
            />
            <button type="button" class="mt-2 text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1" data-testid="note-save-now" @click="flush">
                Save now
            </button>
        </section>

        <!-- Linked entities (create/remove via Knowledge Linking UI) -->
        <section v-if="notes.current" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="note-links">
            <LinkManager :note-id="notes.current.id" />
        </section>
    </div>
</template>
