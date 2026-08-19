<script setup lang="ts">
import { ref } from 'vue';
import TaskListView from './TaskListView.vue';
import TaskDetailView from './TaskDetailView.vue';

const selectedTaskId = ref<number | null>(null);

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
