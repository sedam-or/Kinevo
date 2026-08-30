<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import { useNoteStore } from './store';
import KButton from '../components/KButton.vue';
import KInput from '../components/KInput.vue';

const emit = defineEmits<{
    (e: 'select', noteId: number): void;
}>();

const notes = useNoteStore();

const createForm = reactive({ title: '' });
const createError = ref<string | null>(null);
const searchQuery = ref('');

onMounted(() => {
    void notes.loadList();
});

watch(searchQuery, (q) => {
    if (q.trim() === '') {
        void notes.loadList();
    } else {
        void notes.search(q.trim());
    }
});

function displayedNotes() {
    return searchQuery.value.trim() === '' ? notes.notes : notes.searchResults;
}

async function createNote(): Promise<void> {
    createError.value = null;
    if (createForm.title.trim() === '') {
        return;
    }
    const note = await notes.create(createForm.title.trim());
    if (note === null) {
        createError.value = notes.error?.message ?? 'Could not create note.';
        return;
    }
    createForm.title = '';
    emit('select', note.id);
}
</script>

<template>
    <div class="flex flex-col gap-6" data-testid="notes-view">
        <div>
            <h1 class="text-xl font-semibold">Knowledge · Notes</h1>
            <p class="text-sm text-text-muted">Capture what you learn and link it to your work.</p>
        </div>

        <section class="surface-primary p-5" data-testid="note-create">
            <div class="font-mono text-xs uppercase tracking-widest text-text-muted mb-3">New note</div>
            <form class="flex gap-2" @submit.prevent="createNote">
                <div v-if="createError" class="w-full text-sm text-danger" role="alert">{{ createError }}</div>
                <KInput v-model="createForm.title" placeholder="Note title" class="flex-1" data-testid="note-create-title" />
                <KButton type="submit" variant="primary" data-testid="note-create-submit">Create</KButton>
            </form>
        </section>

        <section data-testid="note-search">
            <label class="flex flex-col gap-1 text-sm w-full sm:max-w-sm">
                Search
                <KInput v-model="searchQuery" type="search" placeholder="Search notes…" data-testid="note-search-input" />
            </label>
        </section>

        <div v-if="notes.loading" class="text-sm text-text-muted" data-testid="notes-loading">Loading…</div>
        <div v-if="notes.error" class="text-sm text-danger" role="alert" data-testid="notes-error">{{ notes.error.message }}</div>

        <section data-testid="note-list" class="surface-supporting flex flex-col">
            <div
                v-if="displayedNotes().length === 0 && !notes.loading"
                class="border-2 border-dashed border-border/40 rounded-sm p-6 text-center text-sm text-text-muted"
                data-testid="note-empty"
            >No notes found. Create a note above to begin capturing knowledge.</div>
            <article
                v-for="note in displayedNotes()"
                :key="note.id"
                class="surface-metadata border-b border-border/20 py-3 flex flex-col gap-0.5"
                data-testid="note-item"
            >
                <button type="button" class="font-medium text-left rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-focus" data-testid="note-open" @click="emit('select', note.id)">
                    {{ note.title }}
                </button>
                <div v-if="note.updated_at" class="font-mono text-[11px] text-text-muted">
                    Updated {{ note.updated_at.slice(0, 10) }}
                </div>
            </article>
        </section>
    </div>
</template>
