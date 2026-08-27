<script setup lang="ts">
import { ref } from 'vue';
import { useFocusTrap } from '../shell/focus-trap';
import { useWorkspaceStore } from './store';
import UpgradeNotice from '../saas/UpgradeNotice.vue';
import KButton from '../components/KButton.vue';
import KIcon from '../components/KIcon.vue';
import KInput from '../components/KInput.vue';

// Native controls follow the theme tokens (TASK-P17-013).
const fieldClass = 'border border-border rounded-sm bg-bg px-3 py-2 text-sm text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus';

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
const limitHit = ref<{ message: string; plan: string; entitlement: string } | null>(null);
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
    limitHit.value = null;
    const ok = await store.create({ name: newName.value.trim(), type: newType.value });
    creating.value = false;
    if (ok) {
        newName.value = '';
    } else if (store.error?.serverCode === 'ENTITLEMENT_LIMIT') {
        // TASK-P23-008 — explain the limit and the path forward.
        limitHit.value = {
            message: store.error.message,
            plan: String((store.error as unknown as Record<string, unknown>).plan ?? 'free'),
            entitlement: String((store.error as unknown as Record<string, unknown>).entitlement ?? 'max_workspaces'),
        };
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
    <div ref="root" class="fixed inset-0 z-[var(--z-modal)] flex items-center justify-center bg-bg/80 p-4 backdrop-blur-[2px]" role="dialog" aria-modal="true" aria-labelledby="ws-title" data-testid="workspace-manager" @click.self="emit('close')">
        <div class="surface-hero w-full max-w-md p-6 sm:p-8 flex flex-col gap-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-border/20 pb-3">
                <h2 id="ws-title" class="text-lg font-bold">Workspaces</h2>
                <button type="button" class="ml-auto rounded-sm p-1 transition-colors hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-focus" data-testid="workspace-manager-close" aria-label="Close" @click="emit('close')"><KIcon name="x-mark" :size="18" /></button>
            </div>

            <!-- Create -->
            <UpgradeNotice v-if="limitHit" :message="limitHit.message" :plan="limitHit.plan" :entitlement="limitHit.entitlement" data-testid="workspace-upgrade-notice" />
            <form class="flex flex-col gap-2 border-b border-border/20 pb-4" @submit.prevent="submitCreate" data-testid="workspace-create-form">
                <label class="text-sm font-semibold flex flex-col gap-1">
                    New workspace
                    <KInput v-model="newName" type="text" placeholder="e.g. Research" data-testid="workspace-create-name" />
                </label>
                <div class="flex items-center gap-2">
                    <select v-model="newType" :class="fieldClass + ' py-1.5'" data-testid="workspace-create-type" aria-label="Workspace type">
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
                <li v-for="w in store.workspaces" :key="w.id" class="surface-metadata border-b border-border/20 pb-3 flex flex-col gap-2 last:border-b-0" :data-testid="`workspace-row-${w.slug}`">
                    <template v-if="editingId === w.id">
                        <KInput v-model="editName" type="text" data-testid="workspace-edit-name" />
                        <textarea v-model="editDescription" rows="2" :class="fieldClass" data-testid="workspace-edit-description" placeholder="Description"></textarea>
                        <div class="flex gap-2">
                            <KButton variant="secondary" :disabled="busy" data-testid="workspace-edit-save" @click="saveEdit">Save</KButton>
                            <KButton variant="ghost" data-testid="workspace-edit-cancel" @click="editingId = null">Cancel</KButton>
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium text-sm flex items-center gap-2">
                                <span v-if="w.accent" class="inline-block h-2 w-2 rounded-full border border-border/40" :style="{ backgroundColor: w.accent }" aria-hidden="true" />
                                {{ w.name }}
                                <span v-if="w.is_default" class="text-xs text-text-muted" data-testid="workspace-row-default">(default)</span>
                            </span>
                            <span class="font-mono text-[10px] uppercase tracking-widest text-text-muted">{{ w.type }}</span>
                        </div>
                        <p v-if="w.description" class="text-xs text-text-muted">{{ w.description }}</p>
                        <div class="flex flex-wrap gap-2 text-sm">
                            <button type="button" class="underline hover:text-primary" :data-testid="`workspace-edit-${w.slug}`" @click="startEdit(w.id, w.name, w.description)">Rename</button>
                            <button v-if="!w.is_default" type="button" class="underline hover:text-primary" :data-testid="`workspace-default-${w.slug}`" @click="store.setDefault(w.id)">Set default</button>
                            <button v-if="!w.is_default" type="button" class="underline text-danger" :data-testid="`workspace-archive-${w.slug}`" @click="store.archive(w.id)">Archive</button>
                        </div>
                    </template>
                </li>
            </ul>

            <!-- Archived -->
            <div v-if="store.archived.length > 0" class="flex flex-col gap-2 border-t border-border/20 pt-3">
                <h3 class="font-mono text-[11px] uppercase tracking-widest text-text-muted">Archived</h3>
                <div v-for="w in store.archived" :key="w.id" class="surface-metadata flex items-center justify-between gap-2 text-sm" :data-testid="`workspace-archived-${w.slug}`">
                    <span>{{ w.name }}</span>
                    <button type="button" class="underline hover:text-primary" :data-testid="`workspace-restore-${w.slug}`" @click="store.restore(w.id)">Restore</button>
                </div>
            </div>
        </div>
    </div>
</template>
