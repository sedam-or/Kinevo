<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useScheduleDraftStore } from './store';
import { formatDate, formatTime } from './date';
import FeatureHelp from '../components/FeatureHelp.vue';
import KButton from '../components/KButton.vue';
import KIcon from '../components/KIcon.vue';

const emit = defineEmits<{
    (e: 'back'): void;
}>();

const sd = useScheduleDraftStore();

const range = reactive({
    from: '',
    to: '',
});

const proposed = ref(false);
const appliedMessage = ref<string | null>(null);
const syncMessage = ref<string | null>(null);
const synced = ref(false);

function iso(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function defaultRange(): void {
    const today = new Date();
    const day = (today.getDay() + 6) % 7;
    const monday = new Date(today);
    monday.setDate(today.getDate() - day);
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    range.from = iso(monday);
    range.to = iso(sunday);
}

defaultRange();

async function propose(): Promise<void> {
    proposed.value = true;
    appliedMessage.value = null;
    sd.clearProposal();
    await sd.propose(range.from, range.to);
}

/**
 * ADR-016 §2.2 — Sync Now: deterministic diff against current reality,
 * never a write. The diff is reviewed and applied through the same
 * explicit Apply / Cancel flow as a manual reschedule.
 */
async function syncNow(): Promise<void> {
    synced.value = true;
    proposed.value = true;
    appliedMessage.value = null;
    syncMessage.value = null;
    const response = await sd.sync();
    if (response === null) {
        return;
    }
    if (response.status === 'no_changes') {
        syncMessage.value = 'Schedule is up to date.';
    } else if (response.status === 'run_in_progress') {
        syncMessage.value = 'Another sync is running — try again in a moment.';
    } else if (response.status === 'proposal' && response.proposal !== null) {
        syncMessage.value = response.proposal.conflict_task_ids.length > 0
            ? 'Changes found — conflicts remain for some tasks.'
            : 'Changes found — review the diff below.';
    }
}

async function apply(): Promise<void> {
    const ok = await sd.applyProposal();
    if (ok) {
        appliedMessage.value = sd.rescheduleApplyResult?.applied
            ? `Reschedule applied at version ${sd.rescheduleApplyResult?.version}.`
            : 'Reschedule was already applied (no changes).';
        syncMessage.value = null;
    }
}

function cancel(): void {
    proposed.value = false;
    synced.value = false;
    sd.clearProposal();
    appliedMessage.value = null;
    syncMessage.value = null;
}
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="reschedule-view">
        <header class="flex items-center gap-2">
            <KButton variant="secondary" type="button" data-testid="reschedule-back" @click="emit('back')"><KIcon name="arrow-left" :size="16" /> Back</KButton>
            <h1 class="text-xl font-semibold">Dynamic Rescheduler</h1>
            <FeatureHelp id="dynamic-rescheduler" title="Dynamic Rescheduler" body="Re-fits unfinished tasks into the rest of your week based on priorities and real capacity. Nothing changes until you review and accept the proposal." />
        </header>

        <section class="surface-secondary p-4" data-testid="reschedule-controls">
            <div class="flex flex-wrap gap-3 items-end">
                <label class="flex flex-col gap-1 text-sm">
                    From
                    <input v-model="range.from" type="date" class="border border-border bg-bg text-text rounded-sm px-3 py-2 text-sm" data-testid="reschedule-from" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    To
                    <input v-model="range.to" type="date" class="border border-border bg-bg text-text rounded-sm px-3 py-2 text-sm" data-testid="reschedule-to" />
                </label>
                <KButton variant="primary" type="button" :disabled="sd.busy" data-testid="reschedule-propose" @click="propose">
                    {{ sd.busy ? 'Proposing…' : 'Propose Reschedule' }}
                </KButton>
                <KButton variant="secondary" type="button" :disabled="sd.busy" data-testid="schedule-sync" @click="syncNow">
                    {{ sd.busy ? 'Syncing…' : 'Sync Now' }}
                </KButton>
            </div>
            <div v-if="sd.error" class="text-sm text-danger mt-2" role="alert" data-testid="reschedule-error">{{ sd.error.message }}</div>
        </section>

        <template v-if="synced && syncMessage">
            <div class="text-sm text-text-muted" role="status" data-testid="sync-status">{{ syncMessage }}</div>
        </template>

        <template v-if="proposed && sd.proposal">
            <div v-if="appliedMessage" class="text-sm text-success" data-testid="reschedule-applied">{{ appliedMessage }}</div>

            <div v-if="!sd.proposalHasChanges" class="text-sm text-text-muted" data-testid="reschedule-no-changes">
                No tasks need to move for this range.
            </div>

            <!-- Moves: BEFORE / AFTER / REASON -->
            <section v-if="sd.proposal.moves.length > 0" data-testid="reschedule-moves">
                <div class="text-xs uppercase text-text-muted mb-2">Proposed changes</div>
                <article v-for="move in sd.proposal.moves" :key="move.task_id" class="surface-secondary p-3 mb-2" data-testid="reschedule-move">
                    <div class="font-medium text-sm mb-1">{{ move.title }}</div>
                    <div class="text-sm">
                        <span class="font-mono text-text-muted uppercase">BEFORE: </span>
                        <span data-testid="move-before">{{ move.from ? `${formatDate(move.from.start)} ${formatTime(move.from.start)}` : 'not scheduled' }}</span>
                    </div>
                    <div class="text-sm">
                        <span class="font-mono text-text-muted uppercase">AFTER: </span>
                        <span data-testid="move-after">{{ formatDate(move.to.start) }} {{ formatTime(move.to.start) }}</span>
                    </div>
                    <div class="text-xs font-mono text-text-muted mt-1" data-testid="move-reason">
                        REASON: freed a slot for higher-priority work / the prior slot is no longer feasible.
                    </div>
                </article>
            </section>

            <!-- Conflicts -->
            <section v-if="sd.proposal.conflict_task_ids.length > 0" class="bg-danger-tint border-l-4 border-danger p-4" data-testid="reschedule-conflicts">
                <div class="text-xs uppercase text-danger mb-2">Conflicts ({{ sd.proposal.conflict_task_ids.length }})</div>
                <p class="text-sm text-danger">Tasks could not be placed and remain flagged for manual review.</p>
            </section>

            <div class="flex flex-wrap gap-2">
                <KButton variant="primary" type="button" :disabled="sd.busy" data-testid="reschedule-apply" @click="apply">
                    {{ sd.busy ? 'Applying…' : 'Apply' }}
                </KButton>
                <KButton variant="secondary" type="button" data-testid="reschedule-cancel" @click="cancel">Cancel</KButton>
            </div>
        </template>
    </div>
</template>
