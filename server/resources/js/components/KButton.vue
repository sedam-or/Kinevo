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
        'inline-flex items-center justify-center gap-2 rounded-sm px-4 py-2 min-h-[44px] text-sm font-medium transition-shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-focus)] disabled:opacity-50 disabled:cursor-not-allowed';
    const variants: Record<Variant, string> = {
        primary:
            'border-2 border-[var(--color-border)] bg-[var(--color-primary)] text-[var(--color-primary-contrast)] shadow-[4px_4px_0_var(--color-border)] hover:shadow-[6px_6px_0_var(--color-border)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0_var(--color-border)]',
        secondary:
            'border border-gray-400 dark:border-gray-500 bg-[var(--color-bg)] text-[var(--color-text)] hover:bg-[var(--color-surface)]',
        danger:
            'border-2 border-[var(--color-danger)] bg-[var(--color-danger)] text-white shadow-[4px_4px_0_var(--color-border)] hover:shadow-[6px_6px_0_var(--color-border)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0_var(--color-border)]',
        ghost: 'border-none text-[var(--color-text)] hover:bg-[var(--color-surface)]',
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