<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useNoteStore } from './store';
import VisualStateBadge from '../visualstate/VisualStateBadge.vue';
import type { VisualStateValue } from '../visualstate/types';

const props = defineProps<{
    noteId: number;
}>();

const emit = defineEmits<{
    (e: 'back'): void;
}>();

const notes = useNoteStore();

const title = ref('');
const content = ref('');

let saveTimer: ReturnType<typeof setTimeout> | null = null;
let dirty = false;

const SAVE_DELAY_MS = 600;

onMounted(async () => {
    await notes.load(props.noteId);
    if (notes.current) {
        title.value = notes.current.title;
        content.value = notes.current.plain_text_cache ?? '';
    }
});

watch([title, content], () => {
    dirty = true;
    scheduleSave();
});

onBeforeUnmount(() => {
    if (saveTimer !== null) {
        clearTimeout(saveTimer);
    }
    if (dirty && notes.current) {
        void notes.save({ title: title.value, plain_text_cache: content.value });
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
    dirty = false;
    await notes.save({ title: title.value, plain_text_cache: content.value });
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

function linkLabel(link: { target_type: string; link_type: string }): string {
    return `${link.target_type} (${link.link_type})`;
}
</script>

<template>
    <div class="flex flex-col gap-4" data-testid="note-detail">
        <header class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button type="button" class="text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1" data-testid="note-back" @click="emit('back')">← Back</button>
                <input
                    v-model="title"
                    type="text"
                    class="text-xl font-semibold bg-transparent border border-transparent focus:border-gray-300 dark:focus:border-gray-600 rounded-sm px-2 py-1"
                    data-testid="note-title-input"
                />
            </div>
            <span data-testid="note-save-state"><VisualStateBadge :state="saveStateBadge" /></span>
        </header>

        <div v-if="notes.loading" class="text-sm text-gray-500" data-testid="note-detail-loading">Loading…</div>
        <div v-if="notes.error" class="text-sm text-[#F53003]" role="alert" data-testid="note-detail-error">
            {{ notes.error.message }}
            <span v-if="notes.saveState === 'conflict'"> — this note was changed elsewhere; reload to reconcile.</span>
        </div>

        <!-- Edit content -->
        <section v-if="notes.current" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="note-editor">
            <label class="flex flex-col gap-1 text-sm">
                Content
                <textarea
                    v-model="content"
                    rows="12"
                    class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2"
                    data-testid="note-content"
                ></textarea>
            </label>
            <button type="button" class="mt-2 text-sm border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1" data-testid="note-save-now" @click="flush">
                Save now
            </button>
        </section>

        <!-- Linked entities -->
        <section v-if="notes.current" class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="note-links">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Linked</div>
            <ul v-if="notes.links.length > 0" class="space-y-1">
                <li v-for="link in notes.links" :key="link.id" class="text-sm" data-testid="note-link-item">
                    {{ linkLabel(link) }} #{{ link.target_id }}
                </li>
            </ul>
            <div v-else class="text-sm text-gray-500 dark:text-gray-400">No links yet.</div>
        </section>
    </div>
</template>
