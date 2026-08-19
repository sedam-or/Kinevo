<script setup lang="ts">
import { ref } from 'vue';
import ScheduleDraftView from './ScheduleDraftView.vue';
import RescheduleView from './RescheduleView.vue';

const mode = ref<'draft' | 'reschedule'>('draft');

function goToReschedule(): void {
    mode.value = 'reschedule';
}

function backToDraft(): void {
    mode.value = 'draft';
}
</script>

<template>
    <RescheduleView v-if="mode === 'reschedule'" @back="backToDraft" />
    <ScheduleDraftView v-else @reschedule="goToReschedule" />
</template>
