<script setup lang="ts">
import { defineAsyncComponent, ref } from 'vue';
import CanvasListView from './CanvasListView.vue';
import { useShellStore } from '../shell/store';

/**
 * Deep-open (TASK-P17-002): a related-entity link may navigate to the Canvas
 * surface with a focus target; consume it once on mount so the linked canvas
 * opens instead of the list.
 */
const shell = useShellStore();
const focused = shell.consumeFocus('canvas');

/**
 * Lazy-load the canvas workspace (and its Excalidraw chunk) by route
 * (design.md §89). Canvas isn't needed on every screen, so Excalidraw should
 * not be in the initial bundle. The async boundary also gives us a visible
 * loading entry state (§34.2) instead of a blank page.
 */
const CanvasWorkspaceView = defineAsyncComponent({
    loader: () => import('./CanvasWorkspaceView.vue'),
    loadingComponent: {
        template: `<div class="text-sm text-text-muted" data-testid="canvas-workspace-loading">Loading Canvas…</div>`,
    },
});

const selectedCanvasId = ref<number | null>(focused);

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
