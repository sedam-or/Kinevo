<script setup lang="ts">
import { onErrorCaptured, ref } from 'vue';

const error = ref<string | null>(null);

onErrorCaptured((err) => {
    error.value = err instanceof Error ? err.message : String(err);
    return false;
});
</script>

<template>
    <div>
        <div v-if="error" class="bg-[#fff2f2] dark:bg-[#1D0002] text-danger px-4 py-2 text-sm" role="alert" data-testid="error-boundary">
            Something went wrong: {{ error }}
        </div>
        <slot />
    </div>
</template>
