import { defineStore } from 'pinia';
import { ref } from 'vue';
import { knowledgeLinkApi } from './api';
import type { ApiError } from '../api/types';
import type {
    CanvasOption,
    GoalOption,
    KnowledgeLink,
    KnowledgeLinkType,
    KnowledgeTargetType,
    MilestoneOption,
    NoteOption,
    ProgramOption,
    TaskOption,
} from './types';

export const useKnowledgeLinkStore = defineStore('knowledgeLink', () => {
    const links = ref<KnowledgeLink[]>([]);
    const goals = ref<GoalOption[]>([]);
    const programs = ref<ProgramOption[]>([]);
    const tasks = ref<TaskOption[]>([]);
    const canvases = ref<CanvasOption[]>([]);
    const notes = ref<NoteOption[]>([]);
    const milestones = ref<MilestoneOption[]>([]);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    async function loadLinks(noteId: number): Promise<void> {
        try {
            const { links: list } = await knowledgeLinkApi.linksForNote(noteId);
            links.value = list;
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function loadContext(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const [g, p, t, c, n] = await Promise.all([
                knowledgeLinkApi.goals(),
                knowledgeLinkApi.programs(),
                knowledgeLinkApi.tasks(),
                knowledgeLinkApi.canvases(),
                knowledgeLinkApi.notes(),
            ]);
            goals.value = g.goals;
            programs.value = p.programs;
            tasks.value = t.tasks;
            canvases.value = c.canvases;
            notes.value = n.notes;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function loadMilestones(goalId: number): Promise<void> {
        try {
            const { milestones: list } = await knowledgeLinkApi.milestones(goalId);
            milestones.value = list;
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function createLink(
        noteId: number,
        targetType: KnowledgeTargetType,
        targetId: number,
        linkType: KnowledgeLinkType,
    ): Promise<boolean> {
        error.value = null;
        try {
            const { link } = await knowledgeLinkApi.createForNote(noteId, {
                target_type: targetType,
                target_id: targetId,
                link_type: linkType,
            });
            links.value = [...links.value, link];
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    async function removeLink(noteId: number, linkId: number): Promise<boolean> {
        error.value = null;
        try {
            await knowledgeLinkApi.removeFromNote(noteId, linkId);
            links.value = links.value.filter((l) => l.id !== linkId);
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    async function loadCanvasLinks(canvasId: number): Promise<void> {
        try {
            const { links: list } = await knowledgeLinkApi.linksForCanvas(canvasId);
            links.value = list;
        } catch (err) {
            error.value = err as ApiError;
        }
    }

    async function createCanvasLink(
        canvasId: number,
        targetType: KnowledgeTargetType,
        targetId: number,
        linkType: KnowledgeLinkType,
    ): Promise<boolean> {
        error.value = null;
        try {
            const { link } = await knowledgeLinkApi.createForCanvas(canvasId, {
                target_type: targetType,
                target_id: targetId,
                link_type: linkType,
            });
            links.value = [...links.value, link];
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    async function removeCanvasLink(canvasId: number, linkId: number): Promise<boolean> {
        error.value = null;
        try {
            await knowledgeLinkApi.removeFromCanvas(canvasId, linkId);
            links.value = links.value.filter((l) => l.id !== linkId);
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    function clear(): void {
        links.value = [];
        goals.value = [];
        programs.value = [];
        tasks.value = [];
        canvases.value = [];
        notes.value = [];
        milestones.value = [];
        error.value = null;
    }

    return {
        links,
        goals,
        programs,
        tasks,
        canvases,
        notes,
        milestones,
        loading,
        error,
        loadLinks,
        loadContext,
        loadMilestones,
        createLink,
        removeLink,
        loadCanvasLinks,
        createCanvasLink,
        removeCanvasLink,
        clear,
    };
});
