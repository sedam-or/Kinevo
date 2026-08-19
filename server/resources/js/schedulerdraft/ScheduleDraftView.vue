<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useScheduleDraftStore } from './store';
import { formatDate, formatTime } from './date';

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

onMounted(isoWeekRange);

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

        <!-- Range + generate -->
        <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="draft-controls">
            <div class="flex flex-wrap gap-3 items-end">
                <label class="flex flex-col gap-1 text-sm">
                    From
                    <input v-model="range.from" type="date" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="draft-from" />
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    To
                    <input v-model="range.to" type="date" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="draft-to" />
                </label>
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 font-medium" :disabled="sd.busy" data-testid="draft-generate" @click="generate">
                    {{ sd.busy ? 'Generating…' : 'Generate Draft' }}
                </button>
            </div>
            <div v-if="sd.error" class="text-sm text-[#F53003] mt-2" role="alert" data-testid="draft-error">{{ sd.error.message }}</div>
        </section>

        <!-- Draft preview -->
        <template v-if="generated && sd.draft">
            <div v-if="appliedMessage" class="text-sm text-green-700 dark:text-green-400" data-testid="draft-applied">{{ appliedMessage }}</div>

            <!-- Accepted tasks -->
            <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="draft-accepted">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Accepted ({{ sd.draft.assignments.length }})</div>
                <ul v-if="sd.draft.assignments.length > 0" class="space-y-1">
                    <li v-for="a in sd.draft.assignments" :key="a.task_id" class="text-sm flex items-center justify-between" data-testid="draft-accepted-item">
                        <span>{{ a.title }}</span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ formatDate(a.start) }} {{ formatTime(a.start) }}–{{ formatTime(a.end) }}</span>
                    </li>
                </ul>
                <div v-else class="text-sm text-gray-500 dark:text-gray-400">No tasks accepted.</div>
            </section>

            <!-- Rejected / unassigned tasks with reason -->
            <section v-if="sd.draft.unassigned.length > 0" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="draft-rejected">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Not scheduled ({{ sd.draft.unassigned.length }})</div>
                <ul class="space-y-1">
                    <li v-for="u in sd.draft.unassigned" :key="u.task_id" class="text-sm" data-testid="draft-rejected-item">
                        {{ u.title }} — <span class="text-[#F53003]">{{ reasonLabel(u.reason) }}</span>
                    </li>
                </ul>
            </section>

            <!-- Reasoning note -->
            <p class="text-xs text-gray-500 dark:text-gray-400" data-testid="draft-reasoning">
                This deterministic draft respects Hard Landscape, locked tasks, deadlines, and the 30% safety reserve.
                Applying it bumps the schedule version; stale applies return 409.
            </p>

            <!-- Apply / Cancel -->
            <div class="flex gap-2">
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 font-medium" :disabled="sd.busy" data-testid="draft-apply" @click="apply">
                    {{ sd.busy ? 'Applying…' : 'Apply Draft' }}
                </button>
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2" data-testid="draft-cancel" @click="cancel">Cancel</button>
                <button type="button" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2" data-testid="draft-reschedule" @click="emit('reschedule')">
                    Dynamic Reschedule
                </button>
            </div>
        </template>
    </div>
</template>
