<script setup lang="ts">
import { ref } from 'vue';
import { useFocusTrap } from '../shell/focus-trap';
import { useWorkspaceStore } from './store';
import KButton from '../components/KButton.vue';

/**
 * Workspace management surface (TASK-P19-010): create, edit (name/description/
 * accent), set default, archive and restore. Single-owner only — no teams,
 * no RBAC (P19-010 contract).
 */
const emit = defineEmits<{ (e: 'close'): void }>();

const store = useWorkspaceStore();
const root = ref<HTMLElement | null>(null);

useFocusTrap(root, () => emit('close'));

const newName = ref('');
const newType = ref('other');
const newError = ref<string | null>(null);
const creating = ref(false);
const editingId = ref<number | null>(null);
const editName = ref('');
const editDescription = ref('');
const busy = ref(false);

async function submitCreate(): Promise<void> {
    newError.value = null;
    if (newName.value.trim() === '') {
        newError.value = 'A workspace name is required.';
        return;
    }
    creating.value = true;
    const ok = await store.create({ name: newName.value.trim(), type: newType.value });
    creating.value = false;
    if (ok) {
        newName.value = '';
    } else {
        newError.value = store.error?.message ?? 'Could not create the workspace.';
    }
}

function startEdit(id: number, name: string, description: string | null): void {
    editingId.value = id;
    editName.value = name;
    editDescription.value = description ?? '';
}

async function saveEdit(): Promise<void> {
    if (editingId.value === null) return;
    busy.value = true;
    await store.update(editingId.value, {
        name: editName.value.trim(),
        description: editDescription.value.trim() === '' ? null : editDescription.value.trim(),
    });
    busy.value = false;
    editingId.value = null;
}
</script>

<template>
    <div ref="root" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-labelledby="ws-title" data-testid="workspace-manager" @click.self="emit('close')">
        <div class="w-full max-w-md rounded-sm border border-gray-300 dark:border-gray-600 bg-surface shadow-rest p-4 flex flex-col gap-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between">
                <h2 id="ws-title" class="font-semibold">Workspaces</h2>
                <button type="button" class="text-sm text-gray-500 hover:text-gray-800 dark:hover:text-gray-200" data-testid="workspace-manager-close" @click="emit('close')">✕</button>
            </div>

            <!-- Create -->
            <form class="flex flex-col gap-2 border-b border-gray-200 dark:border-gray-700 pb-4" @submit.prevent="submitCreate" data-testid="workspace-create-form">
                <label class="text-sm flex flex-col gap-1">
                    New workspace
                    <input v-model="newName" type="text" placeholder="e.g. Research" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="workspace-create-name" />
                </label>
                <div class="flex items-center gap-2">
                    <select v-model="newType" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1.5 text-sm" data-testid="workspace-create-type" aria-label="Workspace type">
                        <option value="personal">Personal</option>
                        <option value="work">Work</option>
                        <option value="research">Research</option>
                        <option value="learning">Learning</option>
                        <option value="other">Other</option>
                    </select>
                    <KButton type="submit" variant="primary" :disabled="creating" data-testid="workspace-create-submit">{{ creating ? 'Creating…' : 'Create' }}</KButton>
                </div>
                <p v-if="newError" class="text-xs text-danger" role="alert" data-testid="workspace-create-error">{{ newError }}</p>
            </form>

            <!-- Active list -->
            <ul class="flex flex-col gap-3" data-testid="workspace-list">
                <li v-for="w in store.workspaces" :key="w.id" class="rounded-sm border border-gray-200 dark:border-gray-700 p-3 flex flex-col gap-2" :data-testid="`workspace-row-${w.slug}`">
                    <template v-if="editingId === w.id">
                        <input v-model="editName" type="text" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1 text-sm" data-testid="workspace-edit-name" />
                        <textarea v-model="editDescription" rows="2" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1 text-sm" data-testid="workspace-edit-description" placeholder="Description"></textarea>
                        <div class="flex gap-2">
                            <button type="button" class="text-sm underline" data-testid="workspace-edit-save" @click="saveEdit">Save</button>
                            <button type="button" class="text-sm underline text-gray-500" @click="editingId = null">Cancel</button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium text-sm flex items-center gap-2">
                                <span v-if="w.accent" class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: w.accent }" aria-hidden="true" />
                                {{ w.name }}
                                <span v-if="w.is_default" class="text-xs text-gray-500 dark:text-gray-400" data-testid="workspace-row-default">(default)</span>
                            </span>
                            <span class="text-xs uppercase text-gray-400">{{ w.type }}</span>
                        </div>
                        <p v-if="w.description" class="text-xs text-gray-500 dark:text-gray-400">{{ w.description }}</p>
                        <div class="flex flex-wrap gap-2 text-sm">
                            <button type="button" class="underline" :data-testid="`workspace-edit-${w.slug}`" @click="startEdit(w.id, w.name, w.description)">Rename</button>
                            <button v-if="!w.is_default" type="button" class="underline" :data-testid="`workspace-default-${w.slug}`" @click="store.setDefault(w.id)">Set default</button>
                            <button v-if="!w.is_default" type="button" class="underline text-danger" :data-testid="`workspace-archive-${w.slug}`" @click="store.archive(w.id)">Archive</button>
                        </div>
                    </template>
                </li>
            </ul>

            <!-- Archived -->
            <div v-if="store.archived.length > 0" class="flex flex-col gap-2 border-t border-gray-200 dark:border-gray-700 pt-3">
                <h3 class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Archived</h3>
                <div v-for="w in store.archived" :key="w.id" class="flex items-center justify-between gap-2 text-sm" :data-testid="`workspace-archived-${w.slug}`">
                    <span>{{ w.name }}</span>
                    <button type="button" class="underline" :data-testid="`workspace-restore-${w.slug}`" @click="store.restore(w.id)">Restore</button>
                </div>
            </div>
        </div>
    </div>
</template>
