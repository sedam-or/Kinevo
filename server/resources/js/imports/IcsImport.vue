<script setup lang="ts">
import { ref } from 'vue';
import { importApi } from './api';
import { formatDate, formatTime } from '../schedulerdraft/date';
import type { IcsImport, IcsImportRow } from './types';
import type { ApiError } from '../api/types';
import KButton from '../components/KButton.vue';

const staged = ref<IcsImport | null>(null);
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
        staged.value = (await importApi.uploadIcs(file)).import;
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
        staged.value = (await importApi.confirmIcs(staged.value.id)).import;
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
        staged.value = (await importApi.discardIcs(staged.value.id)).import;
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

function confidenceLabel(confidence: number | null): string {
    if (confidence === null) {
        return 'n/a';
    }
    return `${Math.round(confidence * 100)}%`;
}

function conflictLabel(row: IcsImportRow): string {
    return row.conflict ? `Conflicts with “${row.conflict_with ?? 'existing schedule'}”` : '';
}
</script>

<template>
    <div class="surface-secondary p-3" data-testid="ics-import">
        <div class="font-mono text-xs uppercase tracking-widest text-text-muted mb-2">Import Calendar (.ics)</div>

        <div v-if="!staged" class="text-sm">
            <p class="text-text-muted mb-2">
                Upload an .ics calendar (e.g. public holiday calendar) to preview its events before they are added as Hard Landscape.
            </p>
            <label class="inline-block border border-dashed border-border/40 hover:border-border rounded-sm px-3 py-1 cursor-pointer text-sm transition-colors disabled:opacity-50" :class="busy ? 'opacity-50' : ''">
                {{ busy ? 'Parsing…' : 'Choose .ics' }}
                <input type="file" accept=".ics,.ical" class="hidden" data-testid="ics-import-file" :disabled="busy" @change="onFileSelected" />
            </label>
            <p class="mt-2 text-xs text-text-muted" data-testid="ics-import-fallback">
                No .ics file available? You can add events manually as Hard Landscape instead.
            </p>
        </div>

        <div v-if="error" class="mt-2 text-sm text-danger" role="alert" data-testid="ics-import-error">
            {{ error }}
        </div>

        <div v-if="staged && staged.status === 'pending'" class="mt-2" data-testid="ics-import-preview">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2 text-sm">
                <span class="text-text-muted">{{ staged.filename }}</span>
                <span class="text-xs text-text-muted">
                    Confidence: {{ confidenceLabel(staged.confidence) }} · {{ staged.rows.length }} events
                </span>
            </div>

            <!-- Per-event parse errors (FR-30 Exception Flow) -->
            <div v-if="staged.errors.length > 0" class="mb-2 text-sm" data-testid="ics-import-errors">
                <p class="text-xs uppercase text-text-muted mb-1">Skipped — could not be read ({{ staged.errors.length }})</p>
                <ul class="space-y-1">
                    <li v-for="(e, index) in staged.errors" :key="`error-${index}`" class="text-danger" data-testid="ics-import-error-item">
                        {{ e.summary ?? 'Event' }} — {{ e.error }}
                    </li>
                </ul>
            </div>

            <!-- Per-event warnings -->
            <div v-if="staged.warnings.length > 0" class="mb-2 text-sm" data-testid="ics-import-warnings">
                <p class="text-xs uppercase text-text-muted mb-1">Noted ({{ staged.warnings.length }})</p>
                <ul class="space-y-1">
                    <li v-for="(w, index) in staged.warnings" :key="`warning-${index}`" class="text-warning" data-testid="ics-import-warning-item">
                        {{ w.summary ?? 'Event' }} — {{ w.warning }}
                    </li>
                </ul>
            </div>

            <table class="w-full text-sm border-collapse" data-testid="ics-import-table">
                <thead>
                    <tr class="text-left text-xs text-text-muted">
                        <th class="py-1 pr-2">Event</th>
                        <th class="py-1 pr-2">When</th>
                        <th class="py-1 pr-2">Type</th>
                        <th class="py-1">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in staged.rows" :key="index" class="border-t border-border/20" data-testid="ics-import-row">
                        <td class="py-1 pr-2">
                            {{ row.summary }}
                            <span v-if="row.location" class="text-xs text-text-muted"> · {{ row.location }}</span>
                        </td>
                        <td class="py-1 pr-2 whitespace-nowrap">
                            {{ formatDate(row.start_at) }} {{ formatTime(row.start_at) }}–{{ formatTime(row.end_at) }}
                        </td>
                        <td class="py-1 pr-2">{{ row.type === 'recurring' ? 'Repeats weekly' : 'One-off' }}</td>
                        <td class="py-1" :class="row.conflict ? 'text-danger' : 'text-text-muted'" data-testid="ics-import-row-conflict">
                            {{ conflictLabel(row) || 'OK' }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="mt-2 text-xs text-text-muted">
                Confirming adds the non-conflicting events as Hard Landscape. Conflicting and unreadable events are skipped — the existing schedule is never overwritten.
            </p>

            <div class="mt-3 flex gap-2">
                <KButton variant="primary" :disabled="busy" data-testid="ics-import-confirm" @click="confirm">
                    {{ busy ? 'Applying…' : 'Confirm & add to schedule' }}
                </KButton>
                <KButton variant="secondary" class="!min-h-0 !px-4 !py-1 text-sm" :disabled="busy" data-testid="ics-import-discard" @click="discard">
                    Discard
                </KButton>
            </div>
        </div>

        <div v-if="staged && staged.status !== 'pending'" class="mt-2 text-sm" data-testid="ics-import-result">
            <p :class="staged.status === 'confirmed' ? 'text-success' : 'text-text-muted'">
                {{ staged.status === 'confirmed' ? 'Import confirmed — non-conflicting events added to the schedule.' : 'Import discarded.' }}
            </p>
            <KButton variant="ghost" class="!min-h-0 !px-3 !py-1 text-sm mt-2" data-testid="ics-import-reset" @click="reset">
                Import another
            </KButton>
        </div>
    </div>
</template>