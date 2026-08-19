<script setup lang="ts">
import { computed } from 'vue';
import { isSchedulerReason, reasonLabel } from './explanation';

const props = defineProps<{
    codes: string[];
}>();

const reasons = computed(() => props.codes.filter(isSchedulerReason).map((code) => ({ code, label: reasonLabel(code) })));
</script>

<template>
    <ul v-if="reasons.length > 0" class="text-xs text-gray-600 dark:text-gray-400 space-y-1" data-testid="scheduler-explanation">
        <li v-for="r in reasons" :key="r.code" class="flex gap-1" data-testid="scheduler-reason">
            <span class="font-mono" :data-code="r.code">{{ r.code }}</span>
            <span>— {{ r.label }}</span>
        </li>
    </ul>
    <p v-else class="text-xs text-gray-500 dark:text-gray-400" data-testid="scheduler-explanation-empty">
        No explanation reasons for this item.
    </p>
</template>
