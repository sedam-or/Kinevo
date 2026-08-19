<script setup lang="ts">
import { ref } from 'vue';
import GoalListView from './GoalListView.vue';
import GoalDetailView from './GoalDetailView.vue';

const selectedGoalId = ref<number | null>(null);

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
