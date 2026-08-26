<script setup lang="ts">
import { onMounted, ref } from 'vue';
import NotesListView from './NotesListView.vue';
import NoteEditView from './NoteEditView.vue';
import { useShellStore } from '../shell/store';

const shell = useShellStore();
const selectedNoteId = ref<number | null>(null);

// TASK-P19-015 — one-shot deep-open: creating a note from a task lands the
// user directly in that note's editor (same plumbing as EntityLinks).
onMounted(() => {
    const focused = shell.consumeFocus('knowledge');
    if (focused !== null) {
        selectedNoteId.value = focused;
    }
});

function select(noteId: number): void {
    selectedNoteId.value = noteId;
}

function back(): void {
    selectedNoteId.value = null;
}
</script>

<template>
    <NoteEditView v-if="selectedNoteId !== null" :note-id="selectedNoteId" @back="back" />
    <NotesListView v-else @select="select" />
</template>
