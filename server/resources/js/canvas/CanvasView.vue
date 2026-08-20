<script setup lang="ts">
import { ref } from 'vue';
import CanvasListView from './CanvasListView.vue';
import CanvasWorkspaceView from './CanvasWorkspaceView.vue';

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