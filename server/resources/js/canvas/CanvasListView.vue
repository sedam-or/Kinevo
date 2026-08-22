<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useCanvasStore } from './store';

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
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    v{{ item.version }}
                </div>
            </article>
        </section>
    </div>
</template>