<script setup lang="ts">
/**
 * Contextual feature explanation (TASK-P17-008/009, design.md §104 P17-E).
 *
 * Two variants:
 * - `icon` (default): inline info control → popover, for feature headers.
 * - `block`: dashed callout rendered INSIDE empty states / as an inline
 *   helper — the explanation is visible immediately, no click needed.
 *
 * In-product education, NOT onboarding slides: "Got it" persists the
 * dismissal locally so it never repeats on this device. Purely a client
 * preference — no server state, no cross-device sync.
 */
import { ref } from 'vue';
// TASK-P20-011 — definitions come from the central registry; inline
// title/body props still win so one-off surfaces can stay local.
import { featureDefinition } from '../features/registry';

const props = withDefaults(
    defineProps<{
        /** Stable feature id — doubles as the localStorage dismissal key. */
        id: string;
        title?: string;
        body?: string;
        /** `icon` = popover trigger; `block` = always-visible callout. */
        variant?: 'icon' | 'block';
    }>(),
    { variant: 'icon' },
);

const registered = featureDefinition(props.id);
const title = props.title ?? registered?.title ?? props.id;
const body = props.body ?? registered?.body ?? '';

const STORAGE_PREFIX = 'kinevo.feature-help.';

const dismissed = ref(readDismissal());
const open = ref(false);

function readDismissal(): boolean {
    try {
        return localStorage.getItem(STORAGE_PREFIX + props.id) === '1';
    } catch {
        return false;
    }
}

function persistDismissal(): void {
    try {
        localStorage.setItem(STORAGE_PREFIX + props.id, '1');
    } catch {
        // Private mode / storage disabled: dismiss for this session only.
    }
}

function toggle(): void {
    open.value = !open.value;
}

function close(): void {
    open.value = false;
}

function dismiss(): void {
    close();
    dismissed.value = true;
    persistDismissal();
}
</script>

<template>
    <!-- Block variant: always-visible explanation inside an empty state. -->
    <span
        v-if="variant === 'block' && !dismissed"
        class="flex flex-col gap-1 border border-dashed border-gray-300 dark:border-gray-600 rounded-sm p-3"
        :data-testid="`feature-help-${id}`"
    >
        <span class="text-sm font-medium">{{ title }}</span>
        <span class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ body }}</span>
        <span class="flex justify-end">
            <button
                type="button"
                class="text-xs border border-border bg-bg rounded-sm px-2 py-1 hover:bg-surface dark:hover:bg-surface-raised focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                :data-testid="`feature-help-${id}-dismiss`"
                @click="dismiss"
            >
                Got it
            </button>
        </span>
    </span>

    <!-- Icon variant: info control + popover. -->
    <span v-else-if="!dismissed" class="relative inline-flex" :data-testid="`feature-help-${id}`">
        <button
            type="button"
            class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 hover:text-text dark:hover:text-text rounded-sm px-1 min-h-[24px] focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
            :aria-expanded="open"
            :aria-controls="`feature-help-${id}-panel`"
            :data-testid="`feature-help-${id}-trigger`"
            @click="toggle"
            @keydown.escape="close"
        >
            <span aria-hidden="true">ⓘ</span>
            <span class="sr-only sm:not-sr-only">What's this?</span>
        </button>

        <span
            v-if="open"
            :id="`feature-help-${id}-panel`"
            role="note"
            class="absolute z-30 right-0 top-7 w-72 border border-border bg-bg rounded-sm shadow-rest p-3 flex flex-col gap-2 text-left"
            :data-testid="`feature-help-${id}-panel`"
        >
            <span class="text-sm font-medium">{{ title }}</span>
            <span class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ body }}</span>
            <span class="flex justify-end">
                <button
                    type="button"
                    class="text-xs border border-border bg-bg rounded-sm px-2 py-1 hover:bg-surface dark:hover:bg-surface-raised focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    :data-testid="`feature-help-${id}-dismiss`"
                    @click="dismiss"
                >
                    Got it
                </button>
            </span>
        </span>
    </span>
</template>
