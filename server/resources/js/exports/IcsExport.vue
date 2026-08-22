<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { downloadScheduleIcs } from './api';

const range = reactive({
    from: '',
    to: '',
});

const busy = ref(false);
const error = ref<string | null>(null);
const done = ref(false);

function currentWeek(): void {
    const today = new Date();
    const day = (today.getDay() + 6) % 7;
    const monday = new Date(today);
    monday.setDate(today.getDate() - day);
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    range.from = toIso(monday);
    range.to = toIso(sunday);
}

function toIso(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

onMounted(currentWeek);

async function exportIcs(): Promise<void> {
    error.value = null;
    done.value = false;

    if (!range.from || !range.to) {
        error.value = 'Select a from and to date.';
        return;
    }

    busy.value = true;
    try {
        await downloadScheduleIcs(range.from, range.to);
        done.value = true;
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Export failed.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="border border-gray-300 dark:border-gray-600 rounded-sm p-3" data-testid="ics-export">
        <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Export Schedule (.ics)</div>

        <div class="flex flex-wrap gap-3 items-end">
            <label class="flex flex-col gap-1 text-sm">
                From
                <input v-model="range.from" type="date" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1" data-testid="ics-export-from" />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                To
                <input v-model="range.to" type="date" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1" data-testid="ics-export-to" />
            </label>
            <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-1 text-sm" :disabled="busy" data-testid="ics-export-download" @click="exportIcs">
                {{ busy ? 'Exporting…' : 'Download .ics' }}
            </button>
        </div>

        <p v-if="error" class="mt-2 text-sm text-danger" role="alert" data-testid="ics-export-error">
            {{ error }}
        </p>
        <p v-if="done" class="mt-2 text-sm text-green-700 dark:text-green-400" data-testid="ics-export-success">
            Schedule exported as kinevo-schedule.ics.
        </p>
    </div>
</template>