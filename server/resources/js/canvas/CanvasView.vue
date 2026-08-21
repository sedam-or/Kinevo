<script setup lang="ts">
import { defineAsyncComponent, ref } from 'vue';
import CanvasListView from './CanvasListView.vue';

/**
 * Lazy-load the canvas workspace (and its Excalidraw chunk) by route
 * (design.md §89). Canvas isn't needed on every screen, so Excalidraw should
 * not be in the initial bundle. The async boundary also gives us a visible
 * loading entry state (§34.2) instead of a blank page.
 */
const CanvasWorkspaceView = defineAsyncComponent({
    loader: () => import('./CanvasWorkspaceView.vue'),
    loadingComponent: {
        template: `<div class="text-sm text-gray-500 dark:text-gray-400" data-testid="canvas-workspace-loading">Loading Canvas…</div>`,
    },
});

const selectedCanvasId = ref<number | null>(null);

function select(canvasId: number): void {
    selectedCanvasId.value = canvasId;
}

function back(): void {
    selectedCanvasId.value = null;
}
</script>

<template>
    <CanvasWorkspaceView v-if="selectedCanvasId !== null" :canvas-id="selectedCanvasId" @back="back" />
    <CanvasListView v-else @select="select" />
</template>
