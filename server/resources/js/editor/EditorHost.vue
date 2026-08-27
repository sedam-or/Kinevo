<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { TiptapEditorAdapter } from './TiptapEditorAdapter';
import type {
    EditorAdapter,
    EditorChange,
    EditorDocument,
    EditorTheme,
    EditorToolbarCommand,
} from './types';

const props = withDefaults(
    defineProps<{
        document?: EditorDocument | null;
        readOnly?: boolean;
        theme?: EditorTheme;
        /** Render the minimal §31 toolbar (Heading/Bold/Italic/Link/List/Task list). */
        toolbar?: boolean;
        adapterFactory?: (element: HTMLElement) => EditorAdapter;
    }>(),
    {
        document: null,
        readOnly: false,
        theme: 'light',
        toolbar: false,
        adapterFactory: undefined,
    },
);

const emit = defineEmits<{
    (e: 'change', change: EditorChange): void;
    (e: 'ready', adapter: EditorAdapter): void;
}>();

const host = ref<HTMLElement | null>(null);
let adapter: EditorAdapter | null = null;
/** Bumped after every change/command so active-state computed re-evaluates. */
const stateNonce = ref(0);

const TOOLBAR_BUTTONS: { label: string; command: EditorToolbarCommand }[] = [
    { label: 'H', command: { type: 'heading', level: 2 } },
    { label: 'B', command: { type: 'bold' } },
    { label: 'I', command: { type: 'italic' } },
    { label: 'Link', command: { type: 'link', url: '' } },
    { label: 'List', command: { type: 'bulletList' } },
    { label: 'Tasks', command: { type: 'taskList' } },
];

const toolbarActive = computed<Record<string, boolean>>(() => {
    void stateNonce.value;
    const map: Record<string, boolean> = {};
    if (!adapter) {
        return map;
    }
    for (const btn of TOOLBAR_BUTTONS) {
        map[btn.label] = adapter.isCommandActive(btn.command);
    }
    return map;
});

function createAdapter(): EditorAdapter {
    if (props.adapterFactory && host.value) {
        return props.adapterFactory(host.value);
    }
    return new TiptapEditorAdapter({ element: host.value as HTMLElement });
}

onMounted(() => {
    if (host.value === null) {
        return;
    }
    adapter = createAdapter();
    adapter.subscribe((change) => {
        stateNonce.value += 1;
        emit('change', change);
    });
    adapter.load(props.document ?? null);
    adapter.setReadOnly(props.readOnly);
    adapter.setTheme(props.theme);
    emit('ready', adapter);
});

/** Apply a toolbar command; the post-transaction change bumps the nonce. */
function runToolbar(label: string): void {
    const button = TOOLBAR_BUTTONS.find((b) => b.label === label);
    if (!button || !adapter) {
        return;
    }
    if (button.command.type === 'link') {
        const href = window.prompt('Link URL');
        if (href !== null) {
            adapter.runCommand({ type: 'link', url: href.trim() === '' ? null : href.trim() });
        }
        return;
    }
    adapter.runCommand(button.command);
}

watch(
    () => props.document,
    (doc) => {
        adapter?.load(doc ?? null);
    },
);

watch(
    () => props.readOnly,
    (enabled) => {
        adapter?.setReadOnly(enabled);
    },
);

watch(
    () => props.theme,
    (theme) => {
        adapter?.setTheme(theme);
    },
);

onBeforeUnmount(() => {
    adapter?.destroy();
    adapter = null;
});
</script>

<template>
    <div class="kinevo-editor-shell">
        <!-- Minimal toolbar (design.md §31): Heading / Bold / Italic / Link / List / Task list. -->
        <div v-if="props.toolbar && !props.readOnly" class="flex flex-wrap gap-1 mb-2" data-testid="editor-toolbar">
            <button
                v-for="btn in TOOLBAR_BUTTONS"
                :key="btn.label"
                type="button"
                class="text-sm border border-border bg-bg text-text rounded-sm px-2 py-1 hover:bg-surface transition-colors"
                :class="toolbarActive[btn.label] ? 'font-semibold underline' : ''"
                :data-testid="`toolbar-${btn.label.toLowerCase()}`"
                @click="runToolbar(btn.label)"
            >
                {{ btn.label }}
            </button>
        </div>
        <div ref="host" class="kinevo-editor-host" data-testid="editor-host"></div>
    </div>
</template>
