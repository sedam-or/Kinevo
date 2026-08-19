<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { TiptapEditorAdapter } from './TiptapEditorAdapter';
import type { EditorAdapter, EditorChange, EditorDocument, EditorTheme } from './types';

const props = withDefaults(
    defineProps<{
        document?: EditorDocument | null;
        readOnly?: boolean;
        theme?: EditorTheme;
        adapterFactory?: (element: HTMLElement) => EditorAdapter;
    }>(),
    {
        document: null,
        readOnly: false,
        theme: 'light',
        adapterFactory: undefined,
    },
);

const emit = defineEmits<{
    (e: 'change', change: EditorChange): void;
    (e: 'ready', adapter: EditorAdapter): void;
}>();

const host = ref<HTMLElement | null>(null);
let adapter: EditorAdapter | null = null;

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
        emit('change', change);
    });
    adapter.load(props.document ?? null);
    adapter.setReadOnly(props.readOnly);
    adapter.setTheme(props.theme);
    emit('ready', adapter);
});

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
    <div ref="host" class="kinevo-editor-host" data-testid="editor-host"></div>
</template>
