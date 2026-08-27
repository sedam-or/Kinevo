<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useNoteStore } from './store';
// Lazy: Tiptap + its deps are the heaviest bundle in the app. Splitting the
// host splits the editor chunk out of the initial shell (§89).
const EditorHost = defineAsyncComponent(() => import('../editor/EditorHost.vue'));
import type { EditorAdapter, EditorChange, EditorDocument } from '../editor/types';
import VisualStateBadge from '../visualstate/VisualStateBadge.vue';
import type { VisualStateValue } from '../visualstate/types';
import LinkManager from '../knowledge/LinkManager.vue';
import KButton from '../components/KButton.vue';
import KIcon from '../components/KIcon.vue';
import AiNotConfiguredNotice from '../ai/AiNotConfiguredNotice.vue';
import { useAiSettingsStore } from '../ai/store';
import { aiApi, type AiProposal } from '../ai/api';

const props = withDefaults(
    defineProps<{
        noteId: number;
        adapterFactory?: (element: HTMLElement) => EditorAdapter;
    }>(),
    {
        adapterFactory: undefined,
    },
);

const emit = defineEmits<{
    (e: 'back'): void;
}>();

const notes = useNoteStore();

const title = ref('');
const editorDocument = ref<EditorDocument | null>(null);

let adapter: EditorAdapter | null = null;
let saveTimer: ReturnType<typeof setTimeout> | null = null;
let dirty = false;
let pendingPlainText = '';
let pendingMarkdown = '';

const SAVE_DELAY_MS = 600;

onMounted(async () => {
    await notes.load(props.noteId);
    if (notes.current) {
        title.value = notes.current.title;
        editorDocument.value = notes.current.document_json as EditorDocument | null;
    }
});

watch(title, () => {
    dirty = true;
    scheduleSave();
});

function onEditorReady(editor: EditorAdapter): void {
    adapter = editor;
}

function onEditorChange(change: EditorChange): void {
    pendingPlainText = change.derived.plainText;
    pendingMarkdown = change.derived.markdown;
    dirty = true;
    scheduleSave();
}

onBeforeUnmount(() => {
    if (saveTimer !== null) {
        clearTimeout(saveTimer);
    }
    if (dirty && notes.current && adapter) {
        void persist();
    }
});

function scheduleSave(): void {
    if (saveTimer !== null) {
        clearTimeout(saveTimer);
    }
    saveTimer = setTimeout(() => {
        void flush();
    }, SAVE_DELAY_MS);
}

async function flush(): Promise<void> {
    if (saveTimer !== null) {
        clearTimeout(saveTimer);
        saveTimer = null;
    }
    if (!dirty || !notes.current) {
        return;
    }
    await persist();
}

async function persist(): Promise<void> {
    if (!notes.current) {
        return;
    }
    dirty = false;

    let documentJson: Record<string, unknown> | null = null;
    let plainText = pendingPlainText;
    let markdown = pendingMarkdown;

    if (adapter) {
        const snapshot = adapter.save(notes.current.version);
        documentJson = snapshot.document as unknown as Record<string, unknown>;
        plainText = snapshot.derived.plainText;
        markdown = snapshot.derived.markdown;
    }

    await notes.save({
        title: title.value,
        document_json: documentJson,
        plain_text_cache: plainText,
        markdown_cache: markdown,
    });
}

const saveStateBadge = ref<VisualStateValue>('saved');

function toVisualState(state: string): VisualStateValue {
    const map: Record<string, VisualStateValue> = {
        saved: 'saved',
        saving: 'syncing',
        error: 'failed',
        offline: 'offline',
        conflict: 'conflict',
    };
    return map[state] ?? 'saved';
}

watch(
    () => notes.saveState,
    (state) => {
        saveStateBadge.value = toVisualState(state);
    },
    { immediate: true },
);

/**
 * Contextual AI entry points (TASK-P17-029, FR-60): summarize and task
 * extraction live where the note lives. Pending edits are flushed first so
 * the AI always sees the latest content; proposals stay pending until the
 * user accepts (FR-62) — extraction creates tasks only then.
 */
const ai = useAiSettingsStore();
const aiGateShown = ref(false);
const aiBusy = ref(false);
const aiStage = ref('');
const aiError = ref<string | null>(null);
const summaryProposal = ref<AiProposal | null>(null);
const extractionProposal = ref<AiProposal | null>(null);
const extractedCount = ref<number | null>(null);

const summaryView = computed(() =>
    summaryProposal.value?.payload.type === 'summary_proposal' ? summaryProposal.value.payload : null,
);
const extractionView = computed(() =>
    extractionProposal.value?.payload.type === 'task_extraction_proposal' ? extractionProposal.value.payload : null,
);

async function runNoteAi(kind: 'summary' | 'extract'): Promise<void> {
    if (aiBusy.value || notes.current === null) {
        return;
    }
    await flush();
    await ai.ensureStatus();
    aiGateShown.value = !ai.generationReady;
    if (aiGateShown.value) {
        return;
    }
    aiBusy.value = true;
    aiStage.value = kind === 'summary' ? 'Summarizing…' : 'Extracting tasks…';
    aiError.value = null;
    try {
        const { proposal } =
            kind === 'summary'
                ? await aiApi.summarizeNote(notes.current.id)
                : await aiApi.extractTasks(notes.current.id);
        if (kind === 'summary') {
            summaryProposal.value = proposal;
        } else {
            extractionProposal.value = proposal;
            extractedCount.value = null;
        }
    } catch (err) {
        aiError.value = (err as { message?: string }).message ?? 'AI request failed.';
    } finally {
        aiBusy.value = false;
        aiStage.value = '';
    }
}

async function acceptExtraction(): Promise<void> {
    if (extractionProposal.value === null || aiBusy.value) {
        return;
    }
    aiBusy.value = true;
    aiError.value = null;
    try {
        const result = await aiApi.acceptProposalWithResult(extractionProposal.value.id);
        extractedCount.value = result.tasks?.length ?? 0;
        extractionProposal.value = null;
    } catch (err) {
        aiError.value = (err as { message?: string }).message ?? 'Could not add tasks.';
    } finally {
        aiBusy.value = false;
    }
}

async function rejectExtraction(): Promise<void> {
    if (extractionProposal.value === null || aiBusy.value) {
        return;
    }
    const proposalId = extractionProposal.value.id;
    extractionProposal.value = null;
    try {
        await aiApi.rejectProposal(proposalId);
    } catch {
        // The panel is already dismissed; a stale pending proposal is harmless.
    }
}
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="note-detail">
        <header class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <KButton variant="ghost" data-testid="note-back" @click="emit('back')"><KIcon name="arrow-left" :size="16" /> Back</KButton>
                <input
                    v-model="title"
                    type="text"
                    class="text-xl font-semibold bg-transparent border border-transparent focus:border-gray-300 dark:focus:border-gray-600 rounded-sm px-2 py-1 min-w-0 flex-1"
                    data-testid="note-title-input"
                />
            </div>
            <span data-testid="note-save-state"><VisualStateBadge :state="saveStateBadge" /></span>
        </header>

        <div v-if="notes.loading" class="text-sm text-gray-500" data-testid="note-detail-loading">Loading…</div>
        <div v-if="notes.error" class="text-sm text-danger" role="alert" data-testid="note-detail-error">
            {{ notes.error.message }}
            <span v-if="notes.saveState === 'conflict'"> — this note was changed elsewhere; reload to reconcile.</span>
        </div>

        <!-- Unified knowledge desk (design.md §30): desktop shows editor + linked
             entities side by side; mobile stacks them (list → editor → context). -->
        <div v-if="notes.current" class="grid gap-4 lg:grid-cols-[1fr_320px]">
            <!-- Edit content (Tiptap via the replaceable EditorAdapter) -->
            <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="note-editor">
                <EditorHost
                    :document="editorDocument"
                    :read-only="false"
                    :toolbar="true"
                    :adapter-factory="props.adapterFactory"
                    @ready="onEditorReady"
                    @change="onEditorChange"
                />
                <button type="button" class="mt-2 text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1" data-testid="note-save-now" @click="flush">
                    Save now
                </button>
            </section>

            <!-- Linked-knowledge context sidebar (design.md §33) -->
            <aside class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="note-links">
                <LinkManager :note-id="notes.current.id" />
            </aside>
        </div>

        <!-- Contextual AI (TASK-P17-029): summarize / extract tasks where the
             note lives — never an omnibus AI page; nothing applies without
             explicit acceptance (FR-62). -->
        <section v-if="notes.current" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="note-ai">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">AI</div>
            <AiNotConfiguredNotice v-if="aiGateShown" class="mb-3" />
            <div v-if="aiError" class="text-sm text-danger mb-3" role="alert" data-testid="note-ai-error">{{ aiError }}</div>
            <div v-if="extractedCount !== null" class="text-sm text-success mb-3" role="status" data-testid="note-ai-extract-done">
                {{ extractedCount }} {{ extractedCount === 1 ? 'task' : 'tasks' }} added from this note.
            </div>
            <div class="flex gap-2 flex-wrap">
                <KButton variant="secondary" :disabled="aiBusy" data-testid="note-ai-summarize" @click="runNoteAi('summary')">
                    Summarize with AI
                </KButton>
                <KButton variant="secondary" :disabled="aiBusy" data-testid="note-ai-extract" @click="runNoteAi('extract')">
                    Extract tasks with AI
                </KButton>
                <span v-if="aiBusy" class="text-sm text-gray-500 self-center" data-testid="note-ai-stage">{{ aiStage }}</span>
            </div>

            <div v-if="summaryView" class="mt-3 border border-gray-200 dark:border-gray-700 rounded-sm p-3" data-testid="note-ai-summary">
                <p class="text-sm">{{ summaryView.summary }}</p>
                <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 mt-2" data-testid="note-ai-summary-points">
                    <li v-for="(point, i) in summaryView.key_points" :key="i">{{ point }}</li>
                </ul>
            </div>

            <div v-if="extractionView" class="mt-3 border border-gray-200 dark:border-gray-700 rounded-sm p-3" data-testid="note-ai-extraction-proposal">
                <ul class="list-disc list-inside text-sm" data-testid="note-ai-extraction-tasks">
                    <li v-for="(task, i) in extractionView.tasks" :key="i">{{ task.title }}</li>
                </ul>
                <div class="flex gap-2 mt-3">
                    <button
                        type="button"
                        class="text-sm border border-[var(--color-primary)] text-[var(--color-primary)] rounded-sm px-3 py-1 disabled:opacity-50"
                        :disabled="aiBusy"
                        data-testid="note-ai-extract-accept"
                        @click="acceptExtraction"
                    >Add {{ extractionView.tasks.length }} {{ extractionView.tasks.length === 1 ? 'task' : 'tasks' }}</button>
                    <button
                        type="button"
                        class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1 disabled:opacity-50"
                        :disabled="aiBusy"
                        data-testid="note-ai-extract-reject"
                        @click="rejectExtraction"
                    >Reject</button>
                </div>
            </div>
        </section>
    </div>
</template>
