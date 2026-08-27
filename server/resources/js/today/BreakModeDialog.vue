<script setup lang="ts">
import { ref } from 'vue';
import { todayApi } from './api';
import { useFocusTrap } from '../shell/focus-trap';
import KButton from '../components/KButton.vue';
import KIcon from '../components/KIcon.vue';
import type { StartBreakResponse } from './types';

/**
 * Break Mode dialog (FR-36, design: Modal → ConfirmationDialog → BreakModeDialog).
 * Confirms a manual break/holiday date range. The covered weeks are tagged
 * exceptional for capacity feedback (FR-49), EOD prompts are suppressed, and
 * the start is logged.
 */

const props = defineProps<{
    date: string;
}>();

const emit = defineEmits<{
    confirmed: [result: StartBreakResponse];
    cancelled: [];
}>();

const startDate = ref(props.date);
const endDate = ref(props.date);
const busy = ref(false);
const error = ref<string | null>(null);

const root = ref<HTMLElement | null>(null);
useFocusTrap(root, cancel);

function endAfterStart(): boolean {
    return endDate.value >= startDate.value;
}

async function confirm(): Promise<void> {
    error.value = null;
    if (!endAfterStart()) {
        error.value = 'The end date cannot precede the start date.';
        return;
    }
    busy.value = true;
    try {
        const result = await todayApi.startBreak({
            start_date: startDate.value,
            end_date: endDate.value,
        });
        emit('confirmed', result);
    } catch (err) {
        error.value = (err as { message?: string }).message ?? 'Break Mode could not be started.';
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
        aria-labelledby="break-mode-title"
        data-testid="break-mode-dialog"
    >
        <div class="surface-hero w-full max-w-md p-6 sm:p-8">
            <header class="mb-5 flex items-start gap-4 border-b border-border/20 pb-3">
                <div class="min-w-0">
                    <div class="text-xs font-semibold text-text-muted">Break Mode</div>
                    <h2 id="break-mode-title" class="text-lg font-bold">Start a Break</h2>
                </div>
                <button
                    type="button"
                    class="ml-auto rounded-sm p-1 transition-colors hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    aria-label="Close"
                    @click="cancel"
                ><KIcon name="x-mark" :size="18" /></button>
            </header>
            <p class="mb-5 text-sm text-text-muted">
                Confirming a break tags the covered weeks as exceptional: notifications are suppressed
                and the weeks are excluded from capacity estimates. One active break at a time.
            </p>

            <form class="flex flex-col gap-3">
                <label class="flex flex-col gap-1 text-sm font-semibold">
                    Start date
                    <input
                        v-model="startDate"
                        type="date"
                        required
                        class="border border-border rounded-sm bg-bg px-3 py-2 text-sm text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                        data-testid="bk-start"
                    />
                </label>
                <label class="flex flex-col gap-1 text-sm font-semibold">
                    End date
                    <input
                        v-model="endDate"
                        type="date"
                        required
                        class="border border-border rounded-sm bg-bg px-3 py-2 text-sm text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                        data-testid="bk-end"
                    />
                </label>
                <div v-if="error" class="text-sm text-danger" role="alert" data-testid="bk-error">{{ error }}</div>

                <div class="flex justify-end gap-3 pt-1 mt-2">
                    <KButton variant="ghost" data-testid="bk-cancel" @click="cancel">
                        Cancel
                    </KButton>
                    <KButton variant="primary" :disabled="busy" data-testid="bk-confirm" @click="confirm">
                        {{ busy ? 'Starting…' : 'Start Break' }}
                    </KButton>
                </div>
            </form>
        </div>
    </div>
</template>