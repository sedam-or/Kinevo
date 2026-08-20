<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { todayApi } from './api';
import type { BoostSetupResponse, SetBoostTargetResponse } from './types';

/**
 * Boost Mode dialog (FR-37/FR-38). Shows the recommendation computed from the
 * Capacity feedback loop, lets the user adjust the target on a slider (capped
 * at the 70% safety limit with an explicit warning), and saves the target with
 * a validity period scoped to the active break. Existing capacity feedback is
 * reused — no capacity calculations are duplicated here.
 */

const props = defineProps<{
    breakPeriodId: number;
    startDate: string;
    endDate: string;
}>();

const emit = defineEmits<{
    saved: [result: SetBoostTargetResponse];
    cancelled: [];
}>();

const loading = ref(true);
const loadError = ref<string | null>(null);
const setup = ref<BoostSetupResponse | null>(null);
const targetPercent = ref(60);
const busy = ref(false);
const error = ref<string | null>(null);
const warning = ref<string | null>(null);

onMounted(load);

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;
    try {
        setup.value = await todayApi.getBoostSetup();
        if (setup.value?.active_target) {
            targetPercent.value = setup.value.active_target.target_percent;
        } else if (setup.value?.recommendation.recommended_target_percent) {
            targetPercent.value = setup.value.recommendation.recommended_target_percent;
        }
    } catch (err) {
        loadError.value = (err as { message?: string }).message ?? 'Boost setup could not be loaded.';
    } finally {
        loading.value = false;
    }
}

const capped = ref(false);

watch(targetPercent, (value) => {
    const cap = setup.value?.safety_cap_percent ?? 70;
    capped.value = value > cap;
    warning.value = capped.value
        ? `The proposed ${value}% exceeds the ${cap}% safety cap; the target will be capped.`
        : null;
});

async function save(): Promise<void> {
    error.value = null;
    busy.value = true;
    try {
        const result = await todayApi.setBoostTarget({
            target_percent: targetPercent.value,
            break_period_id: props.breakPeriodId,
            start_date: props.startDate,
            end_date: props.endDate,
        });
        emit('saved', result);
    } catch (err) {
        error.value = (err as { message?: string }).message ?? 'Boost target could not be saved.';
    } finally {
        busy.value = false;
    }
}

function cancel(): void {
    emit('cancelled');
}
</script>

<template>
    <div
        class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50"
        role="dialog"
        aria-modal="true"
        data-testid="boost-dialog"
    >
        <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-sm p-6 max-w-md w-full">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Boost Mode</div>
            <h2 class="text-lg font-semibold mb-1">Holiday Boost Target</h2>

            <div v-if="loading" class="text-sm text-gray-500 dark:text-gray-400 py-4" data-testid="boost-loading">
                Loading setup…
            </div>

            <div v-else-if="loadError" class="text-sm text-[#F53003]" role="alert" data-testid="boost-load-error">
                {{ loadError }}
            </div>

            <template v-else-if="setup">
                <p v-if="setup.recommendation.eligible" class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    Recommendation: set a target around
                    <strong data-testid="boost-recommended">{{ setup.recommendation.recommended_target_percent }}%</strong>
                    of daily capacity. {{ setup.recommendation.reason }}
                </p>
                <p v-else class="text-sm text-gray-600 dark:text-gray-400 mb-3" data-testid="boost-recommendation-text">
                    {{ setup.recommendation.reason }}
                </p>

                <div class="flex flex-col gap-3">
                    <label class="flex flex-col gap-1 text-sm">
                        Target (% of daily capacity)
                        <input
                            v-model.number="targetPercent"
                            type="range"
                            min="1"
                            max="100"
                            class="w-full"
                            data-testid="boost-slider"
                        />
                        <span class="text-sm" data-testid="boost-percent">{{ targetPercent }}%</span>
                    </label>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Validity: {{ props.startDate }} to {{ props.endDate }}
                    </div>
                    <div v-if="warning" class="text-sm text-[#E8A13A]" role="alert" data-testid="boost-warning">
                        {{ warning }}
                    </div>
                    <div v-if="error" class="text-sm text-[#F53003]" role="alert" data-testid="boost-error">
                        {{ error }}
                    </div>

                    <div class="flex justify-end gap-2 mt-2">
                        <button
                            type="button"
                            class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                            data-testid="boost-cancel"
                            @click="cancel"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm font-medium"
                            :disabled="busy"
                            data-testid="boost-save"
                            @click="save"
                        >
                            {{ busy ? 'Saving…' : 'Save Boost Target' }}
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>