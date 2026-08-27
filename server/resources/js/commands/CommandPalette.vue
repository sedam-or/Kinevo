<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useShellStore } from '../shell/store';
import { NAV_ITEMS } from '../shell/navigation';
import { useWorkspaceStore } from '../workspace/store';
import { apiClient } from '../api/client';

/**
 * Unified command surface (TASK-P20-033). One palette — no second search
 * system: it reuses the existing knowledge search API and adds navigation +
 * workspace-switch commands on top. Opens with Ctrl/Cmd+Shift+K (Quick
 * Capture keeps Ctrl/Cmd+K).
 */
const shell = useShellStore();
const workspaces = useWorkspaceStore();

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();
function close(): void {
    emit('update:open', false);
}
const query = ref('');
const knowledgeResults = ref<Array<{ id: number; title: string }>>([]);
const searching = ref(false);

interface Command {
    id: string;
    label: string;
    hint?: string;
    run: () => void;
}

const navCommands = computed<Command[]>(() =>
    NAV_ITEMS.map((item) => ({
        id: `nav-${item.key}`,
        label: item.label,
        hint: 'Go to',
        run: () => shell.setView(item.key as Parameters<typeof shell.setView>[0]),
    })),
);

const workspaceCommands = computed<Command[]>(() =>
    workspaces.workspaces.map((w) => ({
        id: `ws-${w.id}`,
        label: w.name,
        hint: 'Switch workspace',
        run: () => workspaces.switchTo(w.id),
    })),
);

const allCommands = computed<Command[]>(() => [...navCommands.value, ...workspaceCommands.value]);
const filteredCommands = computed<Command[]>(() => {
    const q = query.value.trim().toLowerCase();
    if (q === '') return allCommands.value.slice(0, 8);
    return allCommands.value.filter((c) => c.label.toLowerCase().includes(q));
});

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watchQuery();
function watchQuery(): void {
    // Re-run knowledge search (debounced) whenever the query changes while open.
    void query;
}

async function runKnowledgeSearch(q: string): Promise<void> {
    if (q.trim().length < 2) {
        knowledgeResults.value = [];
        return;
    }
    searching.value = true;
    try {
        const res = await apiClient.request<{ results: Array<{ id: number; title: string }> }>(
            `/knowledge/search?q=${encodeURIComponent(q)}`,
        );
        knowledgeResults.value = res.results.slice(0, 5);
    } catch {
        knowledgeResults.value = [];
    } finally {
        searching.value = false;
    }
}

function onInput(): void {
    if (searchTimer) clearTimeout(searchTimer);
    const q = query.value;
    searchTimer = setTimeout(() => void runKnowledgeSearch(q), 250);
}

function execute(command: Command): void {
    close();
    query.value = '';
    knowledgeResults.value = [];
    command.run();
}

function openKnowledgeNote(_id: number): void {
    close();
    query.value = '';
    knowledgeResults.value = [];
    shell.setView('knowledge', _id);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    void workspaces.load();
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    if (searchTimer) clearTimeout(searchTimer);
});
</script>

<template>
    <div v-if="props.open" class="fixed inset-0 z-[var(--z-command-palette)] flex items-start justify-center bg-black/40 pt-24 px-4" @click.self="close()">
        <div class="w-full max-w-lg rounded-sm border border-border bg-surface-raised shadow-rest p-3 flex flex-col gap-2" role="dialog" aria-modal="true" aria-label="Command palette" data-testid="command-palette">
            <input
                v-model="query"
                type="text"
                autofocus
                placeholder="Jump to… or search notes"
                class="border border-border rounded-sm px-3 py-2 text-sm bg-bg text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                data-testid="command-input"
                @input="onInput"
            />

            <ul tabindex="0" aria-label="Command results" class="flex flex-col gap-1 max-h-72 overflow-y-auto">
                <li v-for="command in filteredCommands" :key="command.id">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between rounded-sm px-3 py-2 text-left text-sm hover:bg-surface font-semibold min-h-[44px]"
                        :data-testid="`command-${command.id}`"
                        @click="execute(command)"
                    >
                        <span>{{ command.label }}</span>
                        <span v-if="command.hint" class="text-xs text-text-muted">{{ command.hint }}</span>
                    </button>
                </li>
                <li v-if="filteredCommands.length === 0 && knowledgeResults.length === 0 && !searching" class="px-3 py-2 text-xs text-text-muted" data-testid="command-empty">
                    No matches. Type two characters or more to search notes.
                </li>
            </ul>

            <div v-if="knowledgeResults.length > 0" class="border-t border-border/20 pt-2">
                <p class="text-xs uppercase tracking-wide text-text-muted mb-1">Notes</p>
                <button
                    v-for="note in knowledgeResults"
                    :key="note.id"
                    type="button"
                    class="w-full text-left rounded-sm px-3 py-2 text-sm hover:bg-surface font-semibold min-h-[44px]"
                    :data-testid="`command-note-${note.id}`"
                    @click="openKnowledgeNote(note.id)"
                >{{ note.title }}</button>
            </div>
        </div>
    </div>
</template>
