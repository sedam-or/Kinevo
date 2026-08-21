<script setup lang="ts">
import { ref } from 'vue';
import { todayApi } from './api';
import { useFocusTrap } from '../shell/focus-trap';
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
        class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50"
        role="dialog"
        aria-modal="true"
        aria-labelledby="break-mode-title"
        data-testid="break-mode-dialog"
    >
        <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-sm p-6 max-w-md w-full">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Break Mode</div>
            <h2 id="break-mode-title" class="text-lg font-semibold mb-1">Start a Break</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Confirming a break tags the covered weeks as exceptional: notifications are suppressed
                and the weeks are excluded from capacity estimates. One active break at a time.
            </p>

            <form class="flex flex-col gap-3">
                <label class="flex flex-col gap-1 text-sm">
                    Start date
                    <input
                        v-model="startDate"
                        type="date"
                        required
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2"
                        data-testid="bk-start"
                    />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    End date
                    <input
                        v-model="endDate"
                        type="date"
                        required
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2"
                        data-testid="bk-end"
                    />
                </label>
                <div v-if="error" class="text-sm text-[#F53003]" role="alert" data-testid="bk-error">{{ error }}</div>

                <div class="flex justify-end gap-2 mt-2">
                    <button
                        type="button"
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm"
                        data-testid="bk-cancel"
                        @click="cancel"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm font-medium"
                        :disabled="busy"
                        data-testid="bk-confirm"
                        @click="confirm"
                    >
                        {{ busy ? 'Starting…' : 'Start Break' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>