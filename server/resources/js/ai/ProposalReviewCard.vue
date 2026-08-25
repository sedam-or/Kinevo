<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import FeatureHelp from '../components/FeatureHelp.vue';
import { aiApi, type AiProposal, type BreakdownMilestone } from './api';

/**
 * Review surface for a pending goal-breakdown proposal (TASK-P17-004).
 * Emits `pending` so parents can hide duplicate "Break Down with AI"
 * entry points while a proposal awaits a decision (TASK-P17-005).
 */
const props = defineProps<{
    goalId: number;
}>();

const emit = defineEmits<{
    (e: 'accepted'): void;
    (e: 'pending', value: boolean): void;
}>();

const proposal = ref<AiProposal | null>(null);
const loading = ref(false);
const busy = ref(false);
const error = ref<string | null>(null);
const editing = ref(false);
const draft = ref<BreakdownMilestone[]>([]);

/** This card renders only goal-breakdown payloads (TASK-P17-029 union). */
const breakdown = computed(() =>
    proposal.value?.payload.type === 'goal_breakdown_proposal' ? proposal.value.payload : null,
);

async function load(): Promise<void> {
    if (!Number.isFinite(props.goalId) || props.goalId <= 0) {
        return;
    }
    loading.value = true;
    error.value = null;
    try {
        const { proposals } = await aiApi.proposals(props.goalId);
        // Oldest pending wins: one active review at a time per goal.
        proposal.value = proposals.length > 0 ? proposals[proposals.length - 1] : null;
        editing.value = false;
    } catch {
        proposal.value = null;
    } finally {
        loading.value = false;
    }
}

function startEditing(): void {
    if (!breakdown.value) {
        return;
    }
    draft.value = breakdown.value.milestones.map((m) => ({ ...m }));
    editing.value = true;
}

function cancelEditing(): void {
    editing.value = false;
}

async function saveEdits(): Promise<void> {
    if (!proposal.value || !breakdown.value) {
        return;
    }
    busy.value = true;
    error.value = null;
    try {
        const payload = { ...breakdown.value, milestones: draft.value };
        const { proposal: updated } = await aiApi.updateProposal(proposal.value.id, payload);
        proposal.value = updated;
        editing.value = false;
    } catch (err) {
        error.value = errorMessage(err);
    } finally {
        busy.value = false;
    }
}

async function accept(): Promise<void> {
    if (!proposal.value) {
        return;
    }
    busy.value = true;
    error.value = null;
    try {
        await aiApi.acceptProposal(proposal.value.id);
        proposal.value = null;
        editing.value = false;
        emit('accepted');
    } catch (err) {
        error.value = errorMessage(err);
    } finally {
        busy.value = false;
    }
}

async function reject(): Promise<void> {
    if (!proposal.value) {
        return;
    }
    busy.value = true;
    error.value = null;
    try {
        await aiApi.rejectProposal(proposal.value.id);
        proposal.value = null;
        editing.value = false;
    } catch (err) {
        error.value = errorMessage(err);
    } finally {
        busy.value = false;
    }
}

function errorMessage(err: unknown): string {
    const detail = (err as { message?: string; errors?: Record<string, string[]> }).message;
    return typeof detail === 'string' && detail !== '' ? detail : 'The proposal could not be saved.';
}

function formatMinutes(minutes: number | null | undefined): string {
    if (minutes === null || minutes === undefined || minutes <= 0) {
        return '';
    }
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h === 0) {
        return `${m}m`;
    }
    return m === 0 ? `${h}h` : `${h}h ${m}m`;
}

onMounted(load);
watch(() => props.goalId, load);
watch(proposal, (p) => emit('pending', p !== null), { immediate: true });

defineExpose({ load });
</script>

<template>
    <section v-if="breakdown" class="border border-[var(--color-primary)] rounded-sm p-4" data-testid="proposal-review">
        <div class="flex items-center justify-between mb-2">
            <div class="flex flex-wrap items-center gap-2 text-xs uppercase text-gray-500 dark:text-gray-400">
                AI Breakdown Proposal
                <span class="ml-2 rounded-sm bg-[var(--color-primary)]/10 text-[var(--color-primary)] px-1.5 py-0.5 normal-case font-medium" data-testid="proposal-ai-generated-badge">AI GENERATED</span>
                <span class="rounded-sm bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-1.5 py-0.5 normal-case" data-testid="proposal-not-committed-badge">NOT YET COMMITTED</span>
                <FeatureHelp id="ai-proposal" title="AI Breakdown Proposal" class="normal-case" body="AI suggests, you decide. Nothing is applied until you accept — edit milestones freely before accepting." />
                <span v-if="proposal?.decision === 'edited'" class="ml-2 normal-case text-[var(--color-primary)]" data-testid="proposal-edited-badge">edited</span>
            </div>
            <span class="text-xs rounded-sm bg-gray-100 dark:bg-gray-800 px-2 py-0.5">{{ breakdown.milestones.length }} milestones</span>
        </div>

        <div v-if="breakdown.rationale" class="mb-3">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Decision summary</div>
            <p class="text-sm text-gray-700 dark:text-gray-300" data-testid="proposal-rationale">
                {{ breakdown.rationale }}
            </p>
        </div>

        <ul class="space-y-2 mb-3" data-testid="proposal-milestones">
            <li v-for="(m, i) in editing ? draft : breakdown.milestones" :key="i" class="text-sm" :data-testid="`proposal-milestone-${i}`">
                <div v-if="editing" class="flex gap-2 flex-wrap items-center">
                    <input
                        v-model="m.title"
                        type="text"
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1 text-sm flex-1 min-w-[12rem]"
                        :data-testid="`proposal-milestone-title-${i}`"
                        :aria-label="`Milestone ${i + 1} title`"
                    />
                    <input
                        v-model="m.target_date"
                        type="date"
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1 text-sm"
                        :data-testid="`proposal-milestone-date-${i}`"
                        :aria-label="`Milestone ${i + 1} target date`"
                    />
                    <input
                        v-model.number="m.estimated_minutes"
                        type="number"
                        min="0"
                        step="15"
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1 text-sm w-24"
                        :data-testid="`proposal-milestone-minutes-${i}`"
                        :aria-label="`Milestone ${i + 1} estimated minutes`"
                    />
                </div>
                <div v-else class="flex items-start justify-between gap-3">
                    <span>{{ m.title }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        <span v-if="m.target_date">{{ m.target_date }}</span>
                        <span v-if="formatMinutes(m.estimated_minutes)"> · {{ formatMinutes(m.estimated_minutes) }}</span>
                    </span>
                </div>
            </li>
        </ul>

        <ul v-if="breakdown.risks && breakdown.risks.length > 0" class="list-disc ml-5 mb-3 text-sm text-gray-600 dark:text-gray-400" data-testid="proposal-risks">
            <li v-for="(risk, i) in breakdown.risks" :key="i">{{ risk }}</li>
        </ul>

        <!-- Explainability (TASK-P17-027): high-level assumptions, inputs used
             and constraints honoured — concise, never chain-of-thought. -->
        <div v-if="breakdown.assumptions && breakdown.assumptions.length > 0" class="mb-3">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Assumptions</div>
            <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400" data-testid="proposal-assumptions">
                <li v-for="(item, i) in breakdown.assumptions" :key="i">{{ item }}</li>
            </ul>
        </div>
        <div v-if="breakdown.inputs && breakdown.inputs.length > 0" class="mb-3">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Inputs used</div>
            <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400" data-testid="proposal-inputs">
                <li v-for="(item, i) in breakdown.inputs" :key="i">{{ item }}</li>
            </ul>
        </div>
        <div v-if="breakdown.constraints && breakdown.constraints.length > 0" class="mb-3">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Constraints honoured</div>
            <ul class="list-disc ml-5 text-sm text-gray-600 dark:text-gray-400" data-testid="proposal-constraints">
                <li v-for="(item, i) in breakdown.constraints" :key="i">{{ item }}</li>
            </ul>
        </div>

        <p v-if="error" class="text-sm text-danger mb-2" role="alert" data-testid="proposal-error">{{ error }}</p>

        <div class="flex gap-2 flex-wrap">
            <template v-if="!editing">
                <button
                    type="button"
                    class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 disabled:opacity-50"
                    data-testid="proposal-edit"
                    :disabled="busy"
                    @click="startEditing"
                >Edit</button>
            </template>
            <template v-else>
                <button
                    type="button"
                    class="text-sm border border-[var(--color-primary)] rounded-sm px-3 py-1 disabled:opacity-50"
                    data-testid="proposal-save-edits"
                    :disabled="busy"
                    @click="saveEdits"
                >Save edits</button>
                <button
                    type="button"
                    class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 disabled:opacity-50"
                    data-testid="proposal-cancel-edits"
                    :disabled="busy"
                    @click="cancelEditing"
                >Cancel</button>
            </template>
            <button
                type="button"
                class="text-sm bg-[var(--color-primary)] text-white rounded-sm px-3 py-1 disabled:opacity-50"
                data-testid="proposal-accept"
                :disabled="busy"
                @click="accept"
            >Accept</button>
            <button
                type="button"
                class="text-sm border border-danger text-danger rounded-sm px-3 py-1 disabled:opacity-50"
                data-testid="proposal-reject"
                :disabled="busy"
                @click="reject"
            >Reject</button>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
            Nothing is applied until you accept. Accepting creates these milestones on the goal.
        </p>
    </section>
</template>
