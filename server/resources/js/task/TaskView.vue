<script setup lang="ts">
import { ref } from 'vue';
import TaskListView from './TaskListView.vue';
import TaskDetailView from './TaskDetailView.vue';
import { useShellStore } from '../shell/store';

/**
 * Deep-open (TASK-P17-002): a related-entity link may navigate to the Tasks
 * surface with a focus target; consume it once on mount so the linked task
 * opens instead of the list.
 */
const shell = useShellStore();
const focused = shell.consumeFocus('tasks');

const selectedTaskId = ref<number | null>(focused);

function select(taskId: number): void {
    selectedTaskId.value = taskId;
}

function back(): void {
    selectedTaskId.value = null;
}
</script>

<template>
    <TaskDetailView v-if="selectedTaskId !== null" :task-id="selectedTaskId" @back="back" />
    <TaskListView v-else @select="select" />
</template>
