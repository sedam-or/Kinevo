<script setup lang="ts">
/**
 * Shared text input (TASK-R2 component library v0).
 * Styling matches the existing UI baseline (border rounded-sm px-3 py-2) and
 * uses design tokens for border/focus color.
 */
defineOptions({ inheritAttrs: false });

defineProps<{ type?: string }>();

const model = defineModel<string | number | undefined>({ default: undefined });

function onInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    model.value = target.value as string | number | undefined;
}
</script>

<template>
    <input
        v-bind="$attrs"
        :type="type"
        :value="model"
        @input="onInput"
        class="border border-border rounded-sm px-3 py-2 text-sm bg-bg text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
    />
</template>