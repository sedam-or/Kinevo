<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
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

function createAdapter(): CanvasAdapter {
    if (props.adapterFactory) {
        return props.adapterFactory();
    }
    return new ExcalidrawCanvasAdapter();
}

onMounted(() => {
    if (host.value === null) {
        return;
    }
    adapter = createAdapter();
    adapter.subscribe((change) => {
        emit('change', change.scene);
    });
    adapter.mount(host.value);
    adapter.load(props.scene ?? null);
    adapter.setReadOnly(props.readOnly);
    adapter.setTheme(props.theme);
    emit('ready', adapter);
});

watch(
    () => props.scene,
    (scene) => {
        adapter?.load(scene ?? null);
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
    <div ref="host" class="kinevo-canvas-host" data-testid="canvas-host"></div>
</template>