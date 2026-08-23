<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useAdaptiveStore } from './store';
import KButton from '../components/KButton.vue';
import FeatureHelp from '../components/FeatureHelp.vue';

/**
 * Lightweight adaptive-context check-in (design.md §23, SRS FR-40).
 *
 * §23 explicitly forbids a clinical-looking questionnaire. The surface is a
 * single-row energy picker + optional current task, one tap to send. Burnout
 * signal is advisory copy, never a value judgment.
 */
const adaptive = useAdaptiveStore();

const form = reactive({
    energyLevel: null as number | null,
    taskId: null as number | null,
});

const localError = ref<string | null>(null);

onMounted(() => {
    void adaptive.load();
    void adaptive.loadBurnout();
});

function energyLabel(level: number): string {
    if (level <= 3) {
        return 'Low';
    }
    if (level <= 6) {
        return 'Moderate';
    }
    return 'High';
}

async function submit(): Promise<void> {
    localError.value = null;
    if (form.energyLevel === null) {
        return;
    }
    const saved = await adaptive.checkIn({
        energy_level: form.energyLevel,
        task_id: form.taskId ?? null,
    });
    if (saved === null) {
        localError.value = adaptive.error?.message ?? 'Could not record the check-in.';
    }
}
</script>

<template>
    <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="adaptive-context">
        <div class="flex items-center gap-2 mb-2">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Context check-in</div>
            <FeatureHelp id="adaptive-context" title="Adaptive Context" body="A one-tap energy check-in. Kinevo uses it to bend today's plan around how you actually feel — and to warn you before burnout builds up." />
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            How's your energy right now? One tap is enough — no questionnaire.
        </p>

        <div v-if="adaptive.burnout && adaptive.burnout.level !== 'none'" class="text-sm mb-3" data-testid="burnout-signal">
            <span class="font-medium">Protect your capacity.</span>
            <span class="text-gray-600 dark:text-gray-400"> {{ adaptive.burnout.reason }}</span>
        </div>

        <div v-if="localError" class="text-sm text-[#D20812]" role="alert" data-testid="adaptive-error">{{ localError }}</div>

        <form class="flex flex-wrap items-end gap-3" @submit.prevent="submit">
            <fieldset>
                <legend class="text-sm mb-1 sr-only">Energy level</legend>
                <div class="flex gap-1">
                    <button
                        v-for="level in [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]"
                        :key="level"
                        type="button"
                        class="h-8 w-8 text-xs border border-gray-300 dark:border-gray-600 rounded-sm"
                        :class="
                            form.energyLevel === level
                                ? 'bg-[var(--color-primary)] text-[var(--color-primary-contrast)] border-[var(--color-border)]'
                                : 'bg-bg text-text'
                        "
                        :aria-pressed="form.energyLevel === level"
                        :data-testid="`adaptive-energy-${level}`"
                        @click="form.energyLevel = level"
                    >
                        {{ level }}
                    </button>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 block" data-testid="adaptive-energy-label">
                    {{ form.energyLevel === null ? 'Pick a level' : `${energyLabel(form.energyLevel)} (${form.energyLevel}/10)` }}
                </span>
            </fieldset>

            <KButton type="submit" variant="primary" :disabled="form.energyLevel === null || adaptive.saving" data-testid="adaptive-submit">
                {{ adaptive.saving ? 'Saving…' : 'Log' }}
            </KButton>
        </form>
    </section>
</template>