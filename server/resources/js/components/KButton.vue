<script setup lang="ts">
/**
 * Shared primary button (TASK-R2 component library v0, design.md §51).
 *
 * Three variants only: primary / secondary / danger (ghost optional).
 * Styling follows the existing UI baseline and design tokens
 * (docs/design-tokens.md). Full props/emits passthrough to <button>.
 */
import { computed } from 'vue';

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost';

const props = withDefaults(
    defineProps<{
        variant?: Variant;
        type?: 'button' | 'submit' | 'reset';
        disabled?: boolean;
    }>(),
    { variant: 'secondary', type: 'button', disabled: false },
);

    const classes = computed(() => {
        const base =
            'inline-flex items-center justify-center gap-2 rounded-sm px-4 py-2 min-h-[44px] text-sm font-medium transition-shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-focus disabled:opacity-50 disabled:cursor-not-allowed';
        const offsets =
            'active:translate-x-0.5 active:translate-y-0.5 shadow-rest hover:shadow-hover active:shadow-active';
        const variants: Record<Variant, string> = {
            primary: `border-2 border-border bg-primary text-primary-contrast ${offsets}`,
            secondary:
                'border border-border bg-bg text-text hover:bg-surface dark:hover:bg-surface-raised',
            danger: `border-2 border-danger bg-danger text-danger-contrast ${offsets}`,
            ghost: 'border-none text-text hover:bg-surface',
        };
        return `${base} ${variants[props.variant]}`;
    });
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        :class="classes"
        v-bind="$attrs"
    >
        <slot />
    </button>
</template>