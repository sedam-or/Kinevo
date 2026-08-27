<script setup lang="ts">
/**
 * Shared icon component (UI consistency slice, design.md §67).
 *
 * One consistent icon set: outline 24px paths from Heroicons v2 by Tailwind
 * Labs (MIT, see docs/third-party/licenses.md). Icons inherit currentColor,
 * use a fixed 1.5 stroke weight, and are decorative by default — pass an
 * accessible name via aria-label on the consuming control or set
 * `decorative` to false and provide a label when the icon stands alone.
 */
import { computed } from 'vue';

export type IconName =
    | 'arrow-left'
    | 'arrow-path'
    | 'arrow-up-right'
    | 'bell'
    | 'check'
    | 'chevron-left'
    | 'chevron-right'
    | 'exclamation-triangle'
    | 'lock'
    | 'plus'
    | 'x-mark';

const props = withDefaults(
    defineProps<{
        name: IconName;
        size?: number;
        decorative?: boolean;
        label?: string;
    }>(),
    { size: 16, decorative: true, label: undefined },
);

const PATHS: Record<IconName, string> = {
    'arrow-left':
        'M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18',
    'arrow-path':
        'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99',
    'arrow-up-right':
        'M4.5 19.5 19.5 4.5m0 0H8.25m11.25 0v11.25',
    bell:
        'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
    check:
        'M4.5 12.75l6 6 9-13.5',
    'chevron-left': 'M15.75 19.5 8.25 12l7.5-7.5',
    'chevron-right': 'M8.25 4.5l7.5 7.5-7.5 7.5',
    'exclamation-triangle':
        'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
    lock:
        'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
    plus:
        'M12 4.5v15m7.5-7.5h-15',
    'x-mark': 'M6 18 18 6M6 6l12 12',
};

const path = computed(() => PATHS[props.name]);
const a11y = computed(() =>
    props.decorative
        ? { 'aria-hidden': true }
        : { role: 'img', 'aria-label': props.label ?? props.name },
);
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        :width="size"
        :height="size"
        stroke-width="1.5"
        stroke="currentColor"
        class="shrink-0"
        v-bind="a11y"
    >
        <path stroke-linecap="round" stroke-linejoin="round" :d="path" />
    </svg>
</template>
