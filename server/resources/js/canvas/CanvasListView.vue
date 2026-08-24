<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useCanvasStore } from './store';
import AiNotConfiguredNotice from '../ai/AiNotConfiguredNotice.vue';
import { useAiSettingsStore } from '../ai/store';
import { aiApi, type AiProposal } from '../ai/api';

const emit = defineEmits<{
    (e: 'select', canvasId: number): void;
}>();

const canvas = useCanvasStore();

const createForm = reactive({ title: '' });
const createError = ref<string | null>(null);

onMounted(() => {
    void canvas.loadList();
});

async function createCanvas(): Promise<void> {
    createError.value = null;
    if (createForm.title.trim() === '') {
        return;
    }
    const created = await canvas.create(createForm.title.trim());
    if (created === null) {
        createError.value = canvas.error?.message ?? 'Could not create canvas.';
        return;
    }
    createForm.title = '';
    emit('select', created.id);
}

/**
 * Contextual AI (TASK-P17-029, FR-60): suggest a board structure from a
 * plain-language goal. The proposal stays pending until accepted (FR-62);
 * acceptance creates the canvas and opens it.
 */
const ai = useAiSettingsStore();
const suggestGateShown = ref(false);
const suggesting = ref(false);
const suggestError = ref<string | null>(null);
const suggestion = ref<AiProposal | null>(null);
const suggestionView = computed(() =>
    suggestion.value?.payload.type === 'canvas_proposal' ? suggestion.value.payload : null,
);

async function suggestStructure(): Promise<void> {
    if (suggesting.value) {
        return;
    }
    const prompt = suggestForm.prompt.trim();
    if (prompt === '') {
        return;
    }
    await ai.ensureStatus();
    suggestGateShown.value = !ai.generationReady;
    if (suggestGateShown.value) {
        return;
    }
    suggesting.value = true;
    suggestError.value = null;
    try {
        const { proposal } = await aiApi.suggestCanvas(prompt);
        suggestion.value = proposal;
    } catch (err) {
        suggestError.value = (err as { message?: string }).message ?? 'AI request failed.';
    } finally {
        suggesting.value = false;
    }
}

const suggestForm = reactive({ prompt: '' });

async function acceptSuggestion(): Promise<void> {
    if (suggestion.value === null || suggesting.value) {
        return;
    }
    suggesting.value = true;
    suggestError.value = null;
    try {
        const result = await aiApi.acceptProposalWithResult(suggestion.value.id);
        suggestion.value = null;
        suggestForm.prompt = '';
        if (result.canvas?.id) {
            emit('select', result.canvas.id);
        }
    } catch (err) {
        suggestError.value = (err as { message?: string }).message ?? 'Could not create the canvas.';
    } finally {
        suggesting.value = false;
    }
}

async function rejectSuggestion(): Promise<void> {
    if (suggestion.value === null || suggesting.value) {
        return;
    }
    const proposalId = suggestion.value.id;
    suggestion.value = null;
    try {
        await aiApi.rejectProposal(proposalId);
    } catch {
        // Dismissed locally; a stale pending proposal is harmless.
    }
}
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="canvas-view">
        <h1 class="text-xl font-semibold">Canvas</h1>

        <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="canvas-create">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">New canvas</div>
            <form class="flex gap-2" @submit.prevent="createCanvas">
                <div v-if="createError" class="text-sm text-danger" role="alert">{{ createError }}</div>
                <input v-model="createForm.title" type="text" placeholder="Canvas title" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2 text-sm flex-1" data-testid="canvas-create-title" />
                <button type="submit" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 font-medium" data-testid="canvas-create-submit">Create</button>
            </form>
        </section>

        <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="canvas-suggest">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Suggest structure with AI</div>
            <AiNotConfiguredNotice v-if="suggestGateShown" class="mb-3" />
            <div v-if="suggestError" class="text-sm text-danger mb-3" role="alert" data-testid="canvas-suggest-error">{{ suggestError }}</div>
            <form class="flex flex-col gap-2" @submit.prevent="suggestStructure">
                <textarea
                    v-model="suggestForm.prompt"
                    rows="2"
                    placeholder="Describe what this board is for — e.g. 'Plan a 3-day conference: talks, sponsors, logistics'"
                    class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2 text-sm"
                    data-testid="canvas-suggest-prompt"
                ></textarea>
                <button
                    type="submit"
                    class="self-start border border-[var(--color-primary)] text-[var(--color-primary)] rounded-sm px-4 py-1.5 font-medium disabled:opacity-50"
                    :disabled="suggesting || suggestForm.prompt.trim() === ''"
                    data-testid="canvas-suggest-submit"
                >{{ suggesting ? 'Thinking…' : 'Suggest structure' }}</button>
            </form>
            <!-- Nothing is created until acceptance (FR-62). -->
            <div v-if="suggestionView" class="mt-3 border border-gray-200 dark:border-gray-700 rounded-sm p-3" data-testid="canvas-suggest-proposal">
                <div class="text-sm font-medium">{{ suggestionView.title }}</div>
                <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 mt-1" data-testid="canvas-suggest-sections">
                    <li v-for="(section, i) in suggestionView.sections" :key="i">
                        {{ section.name }}<template v-if="section.description"> — {{ section.description }}</template>
                    </li>
                </ul>
                <div class="flex gap-2 mt-3">
                    <button
                        type="button"
                        class="text-sm border border-[var(--color-primary)] text-[var(--color-primary)] rounded-sm px-3 py-1 disabled:opacity-50"
                        :disabled="suggesting"
                        data-testid="canvas-suggest-accept"
                        @click="acceptSuggestion"
                    >Create canvas</button>
                    <button
                        type="button"
                        class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 disabled:opacity-50"
                        :disabled="suggesting"
                        data-testid="canvas-suggest-reject"
                        @click="rejectSuggestion"
                    >Reject</button>
                </div>
            </div>
        </section>

        <div v-if="canvas.loading" class="text-sm text-gray-500" data-testid="canvas-loading">Loading…</div>
        <div v-if="canvas.error" class="text-sm text-danger" role="alert" data-testid="canvas-error">{{ canvas.error.message }}</div>

        <section data-testid="canvas-list">
            <div v-if="canvas.canvases.length === 0 && !canvas.loading" class="text-sm text-gray-500 dark:text-gray-400">No canvases yet.</div>
            <article
                v-for="item in canvas.canvases"
                :key="item.id"
                class="border border-gray-300 dark:border-gray-600 rounded-sm p-3 mb-2"
                data-testid="canvas-item"
            >
                <button type="button" class="font-medium text-left" data-testid="canvas-open" @click="emit('select', item.id)">
                    {{ item.title }}
                </button>
            </article>
        </section>
    </div>
</template>