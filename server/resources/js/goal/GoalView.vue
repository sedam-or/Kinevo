<script setup lang="ts">
import { ref } from 'vue';
import GoalListView from './GoalListView.vue';
import GoalDetailView from './GoalDetailView.vue';
import { useShellStore } from '../shell/store';

/**
 * Deep-open (TASK-P17-002): a related-entity link may navigate to the Goals
 * surface with a focus target; consume it once on mount so the linked goal
 * opens instead of the list.
 */
const shell = useShellStore();
const focused = shell.consumeFocus('goals');

const selectedGoalId = ref<number | null>(focused);

function selectGoal(goalId: number): void {
    selectedGoalId.value = goalId;
}

function back(): void {
    selectedGoalId.value = null;
}
</script>

<template>
    <GoalDetailView v-if="selectedGoalId !== null" :goal-id="selectedGoalId" @back="back" />
    <GoalListView v-else @select-goal="selectGoal" />
</template>
