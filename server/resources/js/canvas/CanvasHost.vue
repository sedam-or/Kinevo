<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { ExcalidrawCanvasAdapter } from './ExcalidrawCanvasAdapter';
import type { CanvasAdapter, CanvasScene, CanvasTheme } from './types';

const props = defineProps<{
    scene?: CanvasScene | null;
    readOnly?: boolean;
    theme?: CanvasTheme;
}>();

const emit = defineEmits<{
    (e: 'change', scene: CanvasScene): void;
    (e: 'ready', adapter: CanvasAdapter): void;
}>();

const host = ref<HTMLElement | null>(null);
let adapter: CanvasAdapter | null = null;

onMounted(() => {
    if (host.value === null) {
        return;
    }
    adapter = new ExcalidrawCanvasAdapter();
    adapter.subscribe((change) => {
        emit('change', change.scene);
    });
    adapter.mount(host.value);
    adapter.load(props.scene ?? null);
    adapter.setReadOnly(props.readOnly ?? false);
    if (props.theme) {
        adapter.setTheme(props.theme);
    }
    emit('ready', adapter);
});

onBeforeUnmount(() => {
    adapter?.destroy();
    adapter = null;
});
</script>

<template>
    <div ref="host" class="kinevo-canvas-host"></div>
</template>
