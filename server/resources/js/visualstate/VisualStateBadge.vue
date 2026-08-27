<script setup lang="ts">
import { computed } from 'vue';
import { visualState, type VisualStateDefinition, type VisualStateValue } from './types';

const props = defineProps<{
    state: VisualStateValue;
    label?: string;
}>();

const def = computed<VisualStateDefinition>(() => visualState(props.state));

const toneClasses: Record<VisualStateDefinition['tone'], string> = {
    neutral: 'bg-surface text-text-muted',
    danger: 'bg-danger-tint text-danger',
    warning: 'bg-warning-tint text-warning',
    info: 'bg-info-tint text-info',
    success: 'bg-success-tint text-success',
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
