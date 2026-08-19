<script setup lang="ts">
import { ref } from 'vue';
import NotesListView from './NotesListView.vue';
import NoteEditView from './NoteEditView.vue';

const selectedNoteId = ref<number | null>(null);

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
