<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useToastStore } from '../components/toast';
import { todayApi } from './api';
import { useFocusTrap } from '../shell/focus-trap';
import KButton from '../components/KButton.vue';
import KIcon from '../components/KIcon.vue';
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

const root = ref<HTMLElement | null>(null);
useFocusTrap(root, cancel);
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
        useToastStore().push('Boost target saved');
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
        ref="root"
        class="fixed inset-0 z-[var(--z-modal)] flex items-center justify-center bg-bg/80 p-4 backdrop-blur-[2px]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="boost-title"
        data-testid="boost-dialog"
    >
        <div class="surface-hero w-full max-w-md p-6 sm:p-8">
            <header class="mb-5 flex items-start gap-4 border-b border-border/20 pb-3">
                <div class="min-w-0">
                    <div class="font-mono text-[11px] uppercase tracking-widest text-text-muted">Boost Mode</div>
                    <h2 id="boost-title" class="text-lg font-bold">Holiday Boost Target</h2>
                </div>
                <button
                    type="button"
                    class="ml-auto rounded-sm p-1 transition-colors hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    aria-label="Close"
                    @click="cancel"
                ><KIcon name="x-mark" :size="18" /></button>
            </header>

            <div v-if="loading" class="py-4 text-sm text-text-muted" data-testid="boost-loading">
                Loading setup…
            </div>

            <div v-else-if="loadError" class="text-sm text-danger" role="alert" data-testid="boost-load-error">
                {{ loadError }}
            </div>

            <template v-else-if="setup">
                <p v-if="setup.recommendation.eligible" class="mb-3 text-sm text-text-muted">
                    Recommendation: set a target around
                    <strong data-testid="boost-recommended">{{ setup.recommendation.recommended_target_percent }}%</strong>
                    of daily capacity. {{ setup.recommendation.reason }}
                </p>
                <p v-else class="mb-3 text-sm text-text-muted" data-testid="boost-recommendation-text">
                    {{ setup.recommendation.reason }}
                </p>

                <div class="flex flex-col gap-3">
                    <label class="flex flex-col gap-1 text-sm font-semibold">
                        Target (% of daily capacity)
                        <input
                            v-model.number="targetPercent"
                            type="range"
                            min="1"
                            max="100"
                            class="w-full accent-primary"
                            data-testid="boost-slider"
                        />
                        <span class="font-mono text-sm font-semibold" data-testid="boost-percent">{{ targetPercent }}%</span>
                    </label>
                    <div class="text-xs text-text-muted">
                        Validity: {{ props.startDate }} to {{ props.endDate }}
                    </div>
                    <div v-if="warning" class="block border-l-4 border-warning bg-surface px-3 py-2 text-sm text-warning" role="alert" data-testid="boost-warning">
                        {{ warning }}
                    </div>
                    <div v-if="error" class="text-sm text-danger" role="alert" data-testid="boost-error">
                        {{ error }}
                    </div>

                    <div class="flex justify-end gap-3 pt-1 mt-2">
                        <KButton variant="ghost" data-testid="boost-cancel" @click="cancel">
                            Cancel
                        </KButton>
                        <KButton variant="primary" :disabled="busy" data-testid="boost-save" @click="save">
                            {{ busy ? 'Saving…' : 'Save Boost Target' }}
                        </KButton>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>