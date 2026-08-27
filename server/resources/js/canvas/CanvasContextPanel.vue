<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useKnowledgeLinkStore } from '../knowledge/store';
import { KNOWLEDGE_LINK_TYPES, type KnowledgeLinkType, type KnowledgeTargetType } from '../knowledge/types';
import type { ApiError } from '../api/types';
import KButton from '../components/KButton.vue';

const props = defineProps<{
    canvasId: number;
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
    void Promise.all([links.loadCanvasLinks(props.canvasId), links.loadContext()]);
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

const CANVAS_TARGET_TYPES: KnowledgeTargetType[] = ['goal', 'milestone', 'program', 'task', 'note'];

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
    if (type === 'note') {
        return links.notes.map((n) => ({ value: n.id, label: n.title }));
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
    const ok = await links.createCanvasLink(props.canvasId, form.targetType as KnowledgeTargetType, Number(form.targetId), form.linkType as KnowledgeLinkType);
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
    await links.removeCanvasLink(props.canvasId, linkId);
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
    if (type === 'note') {
        return links.notes.find((n) => n.id === id)?.title ?? `Note #${id}`;
    }
    if (type === 'milestone') {
        return links.milestones.find((m) => m.id === id)?.title ?? `Milestone #${id}`;
    }
    return `#${id}`;
}
</script>

<template>
    <div data-testid="canvas-context">
        <div class="font-mono text-xs uppercase tracking-widest text-text-muted mb-2">Context</div>

        <div v-if="links.error && !formError" class="text-sm text-danger" role="alert" data-testid="canvas-context-load-error">
            {{ links.error.message }}
        </div>

        <ul v-if="links.links.length > 0" class="mb-3">
            <li
                v-for="link in links.links"
                :key="link.id"
                class="surface-metadata flex items-center justify-between gap-2 py-2 border-b border-border/20"
                data-testid="canvas-context-item"
            >
                <span class="text-sm">
                    <span class="font-medium">{{ targetLabel(link.target_type) }}:</span>
                    <span>{{ linkLabel(link.target_type, link.target_id) }}</span>
                    <span class="font-mono text-[11px] text-text-muted"> · {{ link.link_type.replace('_', ' ') }}</span>
                </span>
                <KButton
                    variant="ghost"
                    class="!min-h-0 !px-2 !py-0.5 text-xs shrink-0"
                    :data-testid="`canvas-context-remove-${link.id}`"
                    @click="removeLink(link.id)"
                >Remove</KButton>
            </li>
        </ul>
        <div v-else class="text-sm text-text-muted mb-3" data-testid="canvas-context-empty">No context links yet.</div>

        <form class="flex flex-col gap-2" @submit.prevent="createLink">
            <div class="flex flex-wrap gap-2">
                <label class="flex flex-col gap-1 text-xs text-text-muted">
                    Type
                    <select v-model="form.targetType" class="border border-border rounded-sm px-2 py-1 text-sm bg-bg text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus" data-testid="canvas-context-target-type">
                        <option value="">Select type…</option>
                        <option v-for="t in CANVAS_TARGET_TYPES" :key="t" :value="t">
                            {{ targetLabel(t) }}
                        </option>
                    </select>
                </label>

                <label v-if="form.targetType === 'milestone'" class="flex flex-col gap-1 text-xs text-text-muted">
                    Goal
                    <select v-model="form.goalId" class="border border-border rounded-sm px-2 py-1 text-sm bg-bg text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus" data-testid="canvas-context-goal">
                        <option value="">Select goal…</option>
                        <option v-for="g in links.goals" :key="g.id" :value="g.id">{{ g.title }}</option>
                    </select>
                </label>

                <label v-if="form.targetType !== ''" class="flex flex-col gap-1 text-xs text-text-muted">
                    Entity
                    <select v-model="form.targetId" class="border border-border rounded-sm px-2 py-1 text-sm bg-bg text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus" data-testid="canvas-context-target-id">
                        <option value="">Select {{ targetLabel(form.targetType) }}…</option>
                        <option v-for="o in targetOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </label>

                <label v-if="form.targetType !== ''" class="flex flex-col gap-1 text-xs text-text-muted">
                    Link type
                    <select v-model="form.linkType" class="border border-border rounded-sm px-2 py-1 text-sm bg-bg text-text focus:outline-none focus-visible:ring-2 focus-visible:ring-focus" data-testid="canvas-context-link-type">
                        <option value="">Select link type…</option>
                        <option v-for="t in KNOWLEDGE_LINK_TYPES" :key="t" :value="t">{{ t.replace('_', ' ') }}</option>
                    </select>
                </label>
            </div>

            <div v-if="formError" class="text-sm text-danger" role="alert" data-testid="canvas-context-form-error">{{ formError }}</div>

            <div class="flex items-center gap-2">
                <KButton
                    type="submit"
                    variant="ghost"
                    class="self-start !min-h-0 !px-3 !py-1 text-sm"
                    :disabled="!canSubmit || submitting"
                    data-testid="canvas-context-submit"
                >
                    {{ submitting ? 'Linking…' : 'Add context' }}
                </KButton>
            </div>
        </form>
    </div>
</template>