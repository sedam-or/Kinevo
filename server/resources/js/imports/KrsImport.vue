<script setup lang="ts">
import { ref } from 'vue';
import { importApi } from './api';
import type { KrsImport, KrsImportRow } from './types';
import type { ApiError } from '../api/types';

const staged = ref<KrsImport | null>(null);
const busy = ref(false);
const error = ref<string | null>(null);

async function onFileSelected(event: Event): Promise<void> {
    error.value = null;
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    input.value = '';

    busy.value = true;
    try {
        staged.value = (await importApi.uploadKrs(file)).import;
    } catch (err) {
        error.value = (err as ApiError).message ?? 'Upload failed.';
    } finally {
        busy.value = false;
    }
}

async function confirm(): Promise<void> {
    if (!staged.value) {
        return;
    }
    error.value = null;
    busy.value = true;
    try {
        staged.value = (await importApi.confirm(staged.value.id)).import;
    } catch (err) {
        error.value = (err as ApiError).message ?? 'Confirmation failed.';
    } finally {
        busy.value = false;
    }
}

async function discard(): Promise<void> {
    if (!staged.value) {
        return;
    }
    error.value = null;
    busy.value = true;
    try {
        staged.value = (await importApi.discard(staged.value.id)).import;
    } catch (err) {
        error.value = (err as ApiError).message ?? 'Discard failed.';
    } finally {
        busy.value = false;
    }
}

function reset(): void {
    staged.value = null;
    error.value = null;
}

function dayLabel(day: string): string {
    const labels: Record<string, string> = {
        senin: 'Senin',
        selasa: 'Selasa',
        rabu: 'Rabu',
        kamis: 'Kamis',
        jumat: 'Jumat',
        sabtu: 'Sabtu',
        minggu: 'Minggu',
    };
    return labels[day] ?? day;
}

function confidenceLabel(confidence: number | null): string {
    if (confidence === null) {
        return 'n/a';
    }
    return `${Math.round(confidence * 100)}%`;
}

function editingRows(): KrsImportRow[] {
    return staged.value?.rows ?? [];
}
</script>

<template>
    <div class="border border-gray-300 dark:border-gray-600 rounded-sm p-3" data-testid="krs-import">
        <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Import KRS (PDF)</div>

        <div v-if="!staged" class="text-sm">
            <p class="text-gray-600 dark:text-gray-400 mb-2">
                Upload a KRS PDF to preview its schedule before it is added as Hard Landscape.
            </p>
            <label class="inline-block border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 cursor-pointer text-sm disabled:opacity-50" :class="busy ? 'opacity-50' : ''">
                {{ busy ? 'Parsing…' : 'Choose PDF' }}
                <input type="file" accept=".pdf" class="hidden" data-testid="krs-import-file" :disabled="busy" @change="onFileSelected" />
            </label>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                No PDF available? You can add courses manually as Hard Landscape instead.
            </p>
        </div>

        <div v-if="error" class="mt-2 text-sm text-[#F53003]" role="alert" data-testid="krs-import-error">
            {{ error }}
        </div>

        <div v-if="staged && staged.status === 'pending'" class="mt-2" data-testid="krs-import-preview">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2 text-sm">
                <span class="text-gray-600 dark:text-gray-400">{{ staged.filename }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">Confidence: {{ confidenceLabel(staged.confidence) }} · {{ staged.rows.length }} rows</span>
            </div>

            <!-- Per-line parse errors (TASK-144) -->
            <div v-if="staged.errors.length > 0" class="mb-2 text-sm" data-testid="krs-import-errors">
                <p class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Skipped — could not be read ({{ staged.errors.length }})</p>
                <ul class="space-y-1">
                    <li v-for="(e, index) in staged.errors" :key="`error-${index}`" class="text-[#F53003]" data-testid="krs-import-error-item">
                        {{ e.line ?? 'Line' }} — {{ e.error }}
                    </li>
                </ul>
            </div>

            <!-- Per-row warnings (TASK-144) -->
            <div v-if="staged.warnings.length > 0" class="mb-2 text-sm" data-testid="krs-import-warnings">
                <p class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Noted ({{ staged.warnings.length }})</p>
                <ul class="space-y-1">
                    <li v-for="(w, index) in staged.warnings" :key="`warning-${index}`" class="text-amber-600 dark:text-amber-400" data-testid="krs-import-warning-item">
                        {{ w.course ?? 'Row' }} — {{ w.warning }}
                    </li>
                </ul>
            </div>

            <table class="w-full text-sm border-collapse" data-testid="krs-import-table">
                <thead>
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400">
                        <th class="py-1 pr-2">Day</th>
                        <th class="py-1 pr-2">Time</th>
                        <th class="py-1 pr-2">Course</th>
                        <th class="py-1">Location</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in editingRows()" :key="index" class="border-t border-gray-200 dark:border-gray-700" data-testid="krs-import-row">
                        <td class="py-1 pr-2">{{ dayLabel(row.day) }}</td>
                        <td class="py-1 pr-2">{{ row.start_time }} – {{ row.end_time }}</td>
                        <td class="py-1 pr-2">{{ row.course }}</td>
                        <td class="py-1">{{ row.location ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Confirming adds these as weekly recurring Hard Landscape events. Existing schedule is never overwritten.
            </p>

            <div class="mt-3 flex gap-2">
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-1 text-sm" :disabled="busy" data-testid="krs-import-confirm" @click="confirm">
                    {{ busy ? 'Applying…' : 'Confirm & add to schedule' }}
                </button>
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-1 text-sm" :disabled="busy" data-testid="krs-import-discard" @click="discard">
                    Discard
                </button>
            </div>
        </div>

        <div v-if="staged && staged.status !== 'pending'" class="mt-2 text-sm" data-testid="krs-import-result">
            <p class="text-gray-600 dark:text-gray-400">
                {{ staged.status === 'confirmed' ? 'Import confirmed — rows added to the schedule.' : 'Import discarded.' }}
            </p>
            <button type="button" class="mt-2 border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 text-sm" data-testid="krs-import-reset" @click="reset">
                Import another
            </button>
        </div>
    </div>
</template>