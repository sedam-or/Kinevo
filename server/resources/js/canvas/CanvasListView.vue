<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useCanvasStore } from './store';
import AiNotConfiguredNotice from '../ai/AiNotConfiguredNotice.vue';
import KButton from '../components/KButton.vue';
import KInput from '../components/KInput.vue';
import KIcon from '../components/KIcon.vue';
import FeatureHelp from '../components/FeatureHelp.vue';
import InlineError from '../components/InlineError.vue';
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
    <div class="flex flex-col gap-6" data-testid="canvas-view">
        <div>
        <div class="flex items-center gap-2">
            <h1 class="text-xl font-semibold">Canvas</h1>
            <FeatureHelp id="canvas" title="Canvas" body="A visual board for planning and synthesis — map an idea, untangle a problem, or sketch the shape of a project before it becomes tasks." />
        </div>
        <p class="text-sm text-text-muted">Visual thinking boards for planning and synthesis.</p>
        </div>

        <section class="surface-primary p-5" data-testid="canvas-create">
            <div class="font-mono text-xs uppercase tracking-widest text-text-muted mb-3">New canvas</div>
            <form class="flex gap-2" @submit.prevent="createCanvas">
                <div v-if="createError" class="w-full text-sm text-danger" role="alert">{{ createError }}</div>
                <KInput v-model="createForm.title" placeholder="Canvas title" class="flex-1" data-testid="canvas-create-title" />
                <KButton type="submit" variant="primary" data-testid="canvas-create-submit">Create</KButton>
            </form>
        </section>

        <section class="surface-secondary p-5" data-testid="canvas-suggest">
            <h2 class="text-sm font-semibold mb-3">Suggest structure with AI</h2>
            <AiNotConfiguredNotice v-if="suggestGateShown" class="mb-3" />
            <div v-if="suggestError" class="text-sm text-danger mb-3" role="alert" data-testid="canvas-suggest-error">{{ suggestError }}</div>
            <form class="flex flex-col gap-2" @submit.prevent="suggestStructure">
                <textarea
                    v-model="suggestForm.prompt"
                    rows="2"
                    placeholder="Describe what this board is for, e.g. 'Plan a 3-day conference: talks, sponsors, logistics'"
                    class="border border-border rounded-sm px-3 py-2 text-sm bg-bg text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    data-testid="canvas-suggest-prompt"
                ></textarea>
                <KButton
                    type="submit"
                    variant="primary"
                    class="self-start"
                    :disabled="suggesting || suggestForm.prompt.trim() === ''"
                    data-testid="canvas-suggest-submit"
                >{{ suggesting ? 'Thinking…' : 'Suggest structure' }}</KButton>
            </form>
            <!-- Nothing is created until acceptance (FR-62). -->
            <div v-if="suggestionView" class="mt-3 surface-primary p-3" data-testid="canvas-suggest-proposal">
                <div class="text-sm font-medium">{{ suggestionView.title }}</div>
                <ul class="list-disc list-inside text-sm mt-1" data-testid="canvas-suggest-sections">
                    <li v-for="(section, i) in suggestionView.sections" :key="i">
                        {{ section.name }}<template v-if="section.description"> · {{ section.description }}</template>
                    </li>
                </ul>
                <div class="flex gap-2 mt-3">
                    <KButton
                        type="button"
                        variant="primary"
                        :disabled="suggesting"
                        data-testid="canvas-suggest-accept"
                        @click="acceptSuggestion"
                    >Create canvas</KButton>
                    <KButton
                        type="button"
                        variant="ghost"
                        :disabled="suggesting"
                        data-testid="canvas-suggest-reject"
                        @click="rejectSuggestion"
                    >Reject</KButton>
                </div>
            </div>
        </section>

        <div v-if="canvas.loading" class="text-sm text-text-muted" data-testid="canvas-loading">Loading…</div>
        <InlineError v-if="canvas.error" :message="canvas.error.message" data-testid="canvas-error" @retry="() => void canvas.loadList()" />

        <section data-testid="canvas-list" class="surface-supporting flex flex-col">
            <div
                v-if="canvas.canvases.length === 0 && !canvas.loading"
                class="border-2 border-dashed border-border/40 rounded-sm p-6 flex flex-col items-center gap-2 text-center text-sm text-text-muted"
                data-testid="canvas-empty"
            >
                <span>No boards yet.</span>
                <FeatureHelp
                    id="canvas-roadmap"
                    variant="block"
                    title="Why draw it out first?"
                    body="A canvas helps you map ideas, plan a project, or untangle a problem visually — before it becomes tasks. Create one above and sketch freely; the board is yours."
                />
            </div>
            <article
                v-for="item in canvas.canvases"
                :key="item.id"
                class="surface-metadata border-b border-border/20 py-3"
                data-testid="canvas-item"
            >
                <button
                    type="button"
                    class="group inline-flex items-center gap-1 font-medium text-left rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                    data-testid="canvas-open"
                    @click="emit('select', item.id)"
                >
                    {{ item.title }}
                    <KIcon name="arrow-up-right" :size="14" class="opacity-40 transition-opacity group-hover:opacity-100" />
                </button>
            </article>
        </section>
    </div>
</template>