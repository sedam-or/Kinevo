<script setup lang="ts">
import { computed } from 'vue';
import { visualState, type VisualStateDefinition, type VisualStateValue } from './types';

const props = defineProps<{
    state: VisualStateValue;
    label?: string;
}>();

const def = computed<VisualStateDefinition>(() => visualState(props.state));

const toneClasses: Record<VisualStateDefinition['tone'], string> = {
    neutral: 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
    danger: 'bg-[#fff2f2] dark:bg-[#1D0002] text-[#F53003]',
    warning: 'bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300',
    info: 'bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300',
    success: 'bg-green-50 dark:bg-green-950 text-green-700 dark:text-green-300',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-sm px-1.5 py-0.5 text-xs border"
        :class="[
            toneClasses[def.tone],
            def.dashed ? 'border-dashed border-current' : 'border-transparent',
        ]"
        :data-state="def.value"
        data-testid="visual-state-badge"
    >
        <span aria-hidden="true">{{ def.glyph }}</span>
        <span>{{ label ?? def.label }}</span>
    </span>
</template>
