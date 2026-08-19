<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import { useNoteStore } from './store';

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
    <div class="flex flex-col gap-4" data-testid="notes-view">
        <h1 class="text-xl font-semibold">Knowledge · Notes</h1>

        <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="note-create">
            <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">New note</div>
            <form class="flex gap-2" @submit.prevent="createNote">
                <div v-if="createError" class="text-sm text-[#F53003]" role="alert">{{ createError }}</div>
                <input v-model="createForm.title" type="text" placeholder="Note title" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2 text-sm flex-1" data-testid="note-create-title" />
                <button type="submit" class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-2 font-medium" data-testid="note-create-submit">Create</button>
            </form>
        </section>

        <section class="border border-gray-300 dark:border-gray-600 rounded-sm p-4" data-testid="note-search">
            <label class="flex flex-col gap-1 text-sm">
                Search
                <input v-model="searchQuery" type="search" placeholder="Search notes…" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="note-search-input" />
            </label>
        </section>

        <div v-if="notes.loading" class="text-sm text-gray-500" data-testid="notes-loading">Loading…</div>
        <div v-if="notes.error" class="text-sm text-[#F53003]" role="alert" data-testid="notes-error">{{ notes.error.message }}</div>

        <section data-testid="note-list">
            <div v-if="displayedNotes().length === 0 && !notes.loading" class="text-sm text-gray-500 dark:text-gray-400">No notes found.</div>
            <article
                v-for="note in displayedNotes()"
                :key="note.id"
                class="border border-gray-300 dark:border-gray-600 rounded-sm p-3 mb-2"
                data-testid="note-item"
            >
                <button type="button" class="font-medium text-left" data-testid="note-open" @click="emit('select', note.id)">
                    {{ note.title }}
                </button>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    v{{ note.version }} · updated {{ note.updated_at?.slice(0, 10) }}
                </div>
            </article>
        </section>
    </div>
</template>
