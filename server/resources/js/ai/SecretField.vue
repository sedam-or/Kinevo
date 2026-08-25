<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Secret input for credentials (TASK-P18-011). Contract:
 * - never pre-filled from any stored value (write-only field);
 * - masked by default with an explicit reveal toggle;
 * - `autocomplete="new-password"` so browsers/password managers never offer
 *   to fill or save provider keys as login credentials;
 * - surfaces only the server-provided non-reversible hint, never a secret.
 */
const props = defineProps<{
    modelValue: string;
    label: string;
    hint?: string | null;
    placeholder?: string;
}>();

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const revealed = ref(false);
const actualType = computed(() => (revealed.value ? 'text' : 'password'));

function onInput(event: Event): void {
    emit('update:modelValue', (event.target as HTMLInputElement).value);
}
</script>

<template>
    <label class="flex flex-col gap-1 text-sm" data-testid="secret-field">
        <span>{{ label }}</span>
        <span class="relative flex">
            <input
                :type="actualType"
                :value="modelValue"
                :placeholder="placeholder ?? '••••••••'"
                autocomplete="new-password"
                spellcheck="false"
                class="w-full border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2 pr-12"
                data-testid="secret-input"
                @input="onInput"
            />
            <button
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                :aria-label="revealed ? 'Hide secret' : 'Reveal secret'"
                data-testid="secret-reveal"
                @click="revealed = !revealed"
            >{{ revealed ? 'Hide' : 'Show' }}</button>
        </span>
        <span v-if="hint" class="text-xs text-gray-500 dark:text-gray-400" data-testid="secret-hint">{{ hint }}</span>
    </label>
</template>
