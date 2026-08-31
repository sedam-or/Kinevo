<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useScheduleDraftStore } from './store';
import { formatDate, formatTime } from './date';
import SchedulerExplanation from '../visualstate/SchedulerExplanation.vue';
import KButton from '../components/KButton.vue';
import KrsImport from '../imports/KrsImport.vue';
import IcsImport from '../imports/IcsImport.vue';
import IcsExport from '../exports/IcsExport.vue';
import { SCHEDULER_REASONS } from '../visualstate/explanation';

const emit = defineEmits<{
    (e: 'reschedule'): void;
}>();

const sd = useScheduleDraftStore();

const range = reactive({
    from: '',
    to: '',
});

const generated = ref(false);
const appliedMessage = ref<string | null>(null);

onMounted(() => {
    isoWeekRange();
    void sd.loadWeeklyDrafts();
});

async function applyWeekly(record: import('./types').ScheduleDraftRecord): Promise<void> {
    await sd.applyWeeklyDraft(record);
    if (sd.weeklyDraftMessage) {
        appliedMessage.value = sd.weeklyDraftMessage;
    }
}

async function discardWeekly(record: import('./types').ScheduleDraftRecord): Promise<void> {
    await sd.discardWeeklyDraft(record);
}

function isoWeekRange(): void {
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

async function generate(): Promise<void> {
    generated.value = true;
    appliedMessage.value = null;
    sd.clearDraft();
    await sd.generate(range.from, range.to);
}

async function apply(): Promise<void> {
    const ok = await sd.applyDraft();
    if (ok) {
        appliedMessage.value = sd.draftApplyResult?.applied
            ? `Draft applied at schedule version ${sd.draftApplyResult?.version}.`
            : 'Draft was already applied (no changes).';
    }
}

function cancel(): void {
    generated.value = false;
    sd.clearDraft();
    appliedMessage.value = null;
}

function reasonLabel(reason: string): string {
    const labels: Record<string, string> = {
        NO_AVAILABLE_SLOT: 'No available slot in the range',
        NO_AVAILABLE_ANCHOR_SLOT: 'No qualifying slot for the anchor',
    };
    return labels[reason] ?? reason;
}
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="schedule-draft-view">
        <h1 class="text-xl font-semibold">Schedule Draft</h1>

        <!-- ADR-016 §2.1 — persisted weekly draft (review-ready, never auto-applied) -->
        <section
            v-for="record in sd.weeklyDrafts"
            :key="record.id"
            class="surface-secondary p-4 border-l-4 border-primary"
            data-testid="weekly-draft-banner"
        >
            <div class="text-xs uppercase text-text-muted mb-1">Weekly draft ready</div>
            <p class="text-sm mb-1">
                Your plan for {{ record.horizon_from }} → {{ record.horizon_to }} is ready for review.
                <span v-if="record.stale" class="text-danger" data-testid="weekly-draft-stale">It is stale — applying will be checked against the latest schedule.</span>
            </p>
            <p class="text-xs text-text-muted mb-2">
                {{ record.payload.draft.assignments.length }} placement(s) proposed;
                {{ record.payload.draft.unassigned.length }} task(s) could not be placed.
            </p>
            <ul v-if="record.payload.draft.assignments.length > 0" class="text-sm space-y-1 mb-2" data-testid="weekly-draft-item">
                <li v-for="a in record.payload.draft.assignments" :key="a.task_id" class="flex items-center justify-between">
                    <span>{{ a.title }}</span>
                    <span class="text-xs font-mono text-text-muted">{{ formatDate(a.start) }} {{ formatTime(a.start) }}–{{ formatTime(a.end) }}</span>
                </li>
            </ul>
            <div class="flex gap-2">
                <KButton variant="primary" type="button" :disabled="sd.busy" data-testid="weekly-draft-apply" @click="applyWeekly(record)">Apply</KButton>
                <KButton variant="secondary" type="button" :disabled="sd.busy" data-testid="weekly-draft-discard" @click="discardWeekly(record)">Cancel</KButton>
            </div>
        </section>

        <div v-if="sd.weeklyDraftMessage" class="text-sm text-success" role="status" data-testid="weekly-draft-applied">{{ sd.weeklyDraftMessage }}</div>

        <!-- Range + generate -->
        <section class="surface-secondary p-4" data-testid="draft-controls">
            <div class="flex flex-wrap gap-3 items-end">
                <label class="flex flex-col gap-1 text-sm">
                    From
                    <input v-model="range.from" type="date" class="border border-border bg-bg text-text rounded-sm px-3 py-2 text-sm" data-testid="draft-from" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    To
                    <input v-model="range.to" type="date" class="border border-border bg-bg text-text rounded-sm px-3 py-2 text-sm" data-testid="draft-to" />
                </label>
                <KButton variant="primary" type="button" :disabled="sd.busy" data-testid="draft-generate" @click="generate">
                    {{ sd.busy ? 'Generating…' : 'Generate Draft' }}
                </KButton>
                <KButton variant="secondary" type="button" data-testid="draft-reschedule" @click="emit('reschedule')">
                    Dynamic Reschedule
                </KButton>
            </div>
            <div v-if="sd.error" class="text-sm text-danger mt-2" role="alert" data-testid="draft-error">{{ sd.error.message }}</div>
        </section>

        <!-- Draft preview -->
        <template v-if="generated && sd.draft">
            <div v-if="appliedMessage" class="text-sm text-success" data-testid="draft-applied">{{ appliedMessage }}</div>

            <!-- Accepted tasks -->
            <section class="border-l-4 border-success p-4" data-testid="draft-accepted">
                <div class="text-xs uppercase text-text-muted mb-2">Accepted ({{ sd.draft.assignments.length }})</div>
                <ul v-if="sd.draft.assignments.length > 0" class="space-y-1">
                    <li v-for="a in sd.draft.assignments" :key="a.task_id" class="text-sm flex items-center justify-between" data-testid="draft-accepted-item">
                        <span>{{ a.title }}</span>
                        <span class="text-xs font-mono text-text-muted">{{ formatDate(a.start) }} {{ formatTime(a.start) }}–{{ formatTime(a.end) }}</span>
                    </li>
                </ul>
                <div v-else class="text-sm text-text-muted">No tasks accepted.</div>
            </section>

            <!-- Rejected / unassigned tasks with reason -->
            <section v-if="sd.draft.unassigned.length > 0" class="bg-danger-tint border-l-4 border-danger p-4" data-testid="draft-rejected">
                <div class="text-xs uppercase text-danger mb-2">Not scheduled ({{ sd.draft.unassigned.length }})</div>
                <ul class="space-y-1">
                    <li v-for="u in sd.draft.unassigned" :key="u.task_id" class="text-sm text-danger" data-testid="draft-rejected-item">
                        {{ u.title }} — {{ reasonLabel(u.reason) }}
                    </li>
                </ul>
            </section>

            <!-- Reasoning note -->
            <p class="text-xs text-text-muted" data-testid="draft-reasoning">
                This plan fits around your Hard Landscape events, locked tasks, deadlines, and your 30% safety reserve.
                If your schedule changed while you were reviewing, you'll be asked to check the latest plan first.
            </p>

            <!-- Scheduler explanation reason codes (FR-63) -->
            <section class="surface-metadata border-t border-border pt-3" data-testid="draft-reason-codes">
                <div class="text-xs uppercase text-text-muted mb-1">Scheduler reason codes</div>
                <SchedulerExplanation :codes="SCHEDULER_REASONS.map((r) => r.code)" />
            </section>

            <!-- Apply / Cancel -->
            <div class="flex flex-wrap gap-2">
                <KButton variant="primary" type="button" :disabled="sd.busy" data-testid="draft-apply" @click="apply">
                    {{ sd.busy ? 'Applying…' : 'Apply Draft' }}
                </KButton>
                <KButton variant="secondary" type="button" data-testid="draft-cancel" @click="cancel">Cancel</KButton>
            </div>
        </template>

        <!-- KRS PDF import (FR-24) -->
        <KrsImport />

        <!-- iCalendar import (FR-30) -->
        <IcsImport />

        <!-- iCalendar export (FR-30 / TASK-143) -->
        <IcsExport />
    </div>
</template>
