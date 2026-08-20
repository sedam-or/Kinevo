<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useKnowledgeLinkStore } from './store';
import { KNOWLEDGE_LINK_TYPES, type KnowledgeLinkType, type KnowledgeTargetType } from './types';
import type { ApiError } from '../api/types';

const props = defineProps<{
    noteId: number;
}>();

const links = useKnowledgeLinkStore();

const form = reactive<{
    targetType: KnowledgeTargetType | '';
    targetId: number | '';
    linkType: KnowledgeLinkType | '';
    goalId: number | '';
}>({
    targetType: '',
    targetId: '',
    linkType: '',
    goalId: '',
});

const formError = ref<string | null>(null);
const submitting = ref(false);

onMounted(() => {
    void Promise.all([links.loadLinks(props.noteId), links.loadContext()]);
});

watch(
    () => form.targetType,
    (type) => {
        form.targetId = '';
        if (type !== 'milestone') {
            form.goalId = '';
            links.milestones = [];
        }
    },
);

watch(
    () => form.goalId,
    async (goalId) => {
        form.targetId = '';
        if (goalId === '') {
            links.milestones = [];
            return;
        }
        await links.loadMilestones(Number(goalId));
    },
);

function targetLabel(type: KnowledgeTargetType): string {
    const map: Record<KnowledgeTargetType, string> = {
        goal: 'Goal',
        milestone: 'Milestone',
        program: 'Program',
        task: 'Task',
        canvas: 'Canvas',
        note: 'Note',
    };
    return map[type];
}

const targetOptions = computed<{ value: number; label: string }[]>(() => {
    const type = form.targetType;
    if (type === '') {
        return [];
    }
    if (type === 'goal') {
        return links.goals.map((g) => ({ value: g.id, label: g.title }));
    }
    if (type === 'program') {
        return links.programs.map((p) => ({ value: p.id, label: p.name }));
    }
    if (type === 'task') {
        return links.tasks.map((t) => ({ value: t.id, label: t.title }));
    }
    if (type === 'canvas') {
        return links.canvases.map((c) => ({ value: c.id, label: c.title }));
    }
    if (type === 'milestone') {
        return links.milestones.map((m) => ({ value: m.id, label: m.title }));
    }
    return [];
});

const canSubmit = computed(
    () =>
        form.targetType !== '' &&
        form.targetId !== '' &&
        form.linkType !== '' &&
        (form.targetType !== 'milestone' || form.goalId !== ''),
);

async function createLink(): Promise<void> {
    formError.value = null;
    if (!canSubmit.value) {
        return;
    }
    submitting.value = true;
    const ok = await links.createLink(props.noteId, form.targetType as KnowledgeTargetType, Number(form.targetId), form.linkType as KnowledgeLinkType);
    submitting.value = false;
    if (!ok) {
        const err = links.error as ApiError | null;
        formError.value = err?.status === 409 ? 'This link already exists.' : (err?.message ?? 'Could not create the link.');
        return;
    }
    form.targetId = '';
    form.linkType = '';
}

async function removeLink(linkId: number): Promise<void> {
    formError.value = null;
    await links.removeLink(props.noteId, linkId);
    if (links.error) {
        formError.value = (links.error as ApiError).message ?? 'Could not remove the link.';
    }
}

function linkLabel(type: KnowledgeTargetType, id: number): string {
    if (type === 'goal') {
        return links.goals.find((g) => g.id === id)?.title ?? `Goal #${id}`;
    }
    if (type === 'program') {
        return links.programs.find((p) => p.id === id)?.name ?? `Program #${id}`;
    }
    if (type === 'task') {
        return links.tasks.find((t) => t.id === id)?.title ?? `Task #${id}`;
    }
    if (type === 'canvas') {
        return links.canvases.find((c) => c.id === id)?.title ?? `Canvas #${id}`;
    }
    if (type === 'milestone') {
        return links.milestones.find((m) => m.id === id)?.title ?? `Milestone #${id}`;
    }
    return `#${id}`;
}
</script>

<template>
    <div data-testid="knowledge-links">
        <div class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Linked entities</div>

        <div v-if="links.error && !formError" class="text-sm text-[#F53003]" role="alert" data-testid="links-load-error">
            {{ links.error.message }}
        </div>

        <ul v-if="links.links.length > 0" class="space-y-1 mb-3">
            <li
                v-for="link in links.links"
                :key="link.id"
                class="flex items-center justify-between gap-2 border border-gray-200 dark:border-gray-700 rounded-sm px-3 py-2"
                data-testid="knowledge-link-item"
            >
                <span class="text-sm">
                    <span class="font-medium">{{ targetLabel(link.target_type) }}:</span>
                    <span class="text-gray-600 dark:text-gray-300">{{ linkLabel(link.target_type, link.target_id) }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400"> · {{ link.link_type.replace('_', ' ') }}</span>
                </span>
                <button
                    type="button"
                    class="text-xs border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-0.5"
                    :data-testid="`link-remove-${link.id}`"
                    @click="removeLink(link.id)"
                >
                    Remove
                </button>
            </li>
        </ul>
        <div v-else class="text-sm text-gray-500 dark:text-gray-400 mb-3" data-testid="no-links">No links yet.</div>

        <form class="flex flex-col gap-2" @submit.prevent="createLink">
            <div class="flex flex-wrap gap-2">
                <label class="flex flex-col gap-1 text-xs text-gray-500 dark:text-gray-400">
                    Type
                    <select v-model="form.targetType" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1 text-sm" data-testid="link-target-type">
                        <option value="">Select type…</option>
                        <option v-for="t in ['goal', 'milestone', 'program', 'task', 'canvas']" :key="t" :value="t">
                            {{ targetLabel(t as KnowledgeTargetType) }}
                        </option>
                    </select>
                </label>

                <label v-if="form.targetType === 'milestone'" class="flex flex-col gap-1 text-xs text-gray-500 dark:text-gray-400">
                    Goal
                    <select v-model="form.goalId" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1 text-sm" data-testid="link-goal">
                        <option value="">Select goal…</option>
                        <option v-for="g in links.goals" :key="g.id" :value="g.id">{{ g.title }}</option>
                    </select>
                </label>

                <label v-if="form.targetType !== ''" class="flex flex-col gap-1 text-xs text-gray-500 dark:text-gray-400">
                    Entity
                    <select v-model="form.targetId" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1 text-sm" data-testid="link-target-id">
                        <option value="">Select {{ targetLabel(form.targetType) }}…</option>
                        <option v-for="o in targetOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </label>

                <label v-if="form.targetType !== ''" class="flex flex-col gap-1 text-xs text-gray-500 dark:text-gray-400">
                    Link type
                    <select v-model="form.linkType" class="border border-gray-300 dark:border-gray-600 rounded-sm px-2 py-1 text-sm" data-testid="link-type">
                        <option value="">Select link type…</option>
                        <option v-for="t in KNOWLEDGE_LINK_TYPES" :key="t" :value="t">{{ t.replace('_', ' ') }}</option>
                    </select>
                </label>
            </div>

            <div v-if="formError" class="text-sm text-[#F53003]" role="alert" data-testid="link-form-error">{{ formError }}</div>

            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    :disabled="!canSubmit || submitting"
                    class="border border-gray-300 dark:border-gray-600 rounded-sm px-4 py-1 text-sm font-medium disabled:opacity-50"
                    data-testid="link-submit"
                >
                    {{ submitting ? 'Linking…' : 'Add link' }}
                </button>
            </div>
        </form>
    </div>
</template>
