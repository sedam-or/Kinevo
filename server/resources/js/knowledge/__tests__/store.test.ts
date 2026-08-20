import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        knowledgeLinkApi: {
            linksForNote: vi.fn(),
            createForNote: vi.fn(),
            removeFromNote: vi.fn(),
            linksForCanvas: vi.fn(),
            createForCanvas: vi.fn(),
            removeFromCanvas: vi.fn(),
            reverseLinks: vi.fn(),
            goals: vi.fn(),
            programs: vi.fn(),
            tasks: vi.fn(),
            milestones: vi.fn(),
            canvases: vi.fn(),
            notes: vi.fn(),
        },
    };
});

import { useKnowledgeLinkStore } from '../store';
import { knowledgeLinkApi } from '../api';
import type { KnowledgeLink } from '../types';

const link: KnowledgeLink = {
    id: 1,
    user_id: 1,
    source_type: 'note',
    source_id: 1,
    target_type: 'goal',
    target_id: 9,
    link_type: 'supports',
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('knowledge link store', () => {
    it('loads links for a note', async () => {
        vi.mocked(knowledgeLinkApi.linksForNote).mockResolvedValue({ links: [link] });
        const store = useKnowledgeLinkStore();
        await store.loadLinks(1);
        expect(store.links).toHaveLength(1);
        expect(store.links[0].target_type).toBe('goal');
    });

    it('loads context options', async () => {
        vi.mocked(knowledgeLinkApi.goals).mockResolvedValue({ goals: [{ id: 1, title: 'G' }] });
        vi.mocked(knowledgeLinkApi.programs).mockResolvedValue({ programs: [{ id: 1, name: 'P' }] });
        vi.mocked(knowledgeLinkApi.tasks).mockResolvedValue({ tasks: [{ id: 1, title: 'T' }] });
        vi.mocked(knowledgeLinkApi.canvases).mockResolvedValue({ canvases: [{ id: 1, title: 'C' }] });
        vi.mocked(knowledgeLinkApi.notes).mockResolvedValue({ notes: [{ id: 1, title: 'N' }] });
        const store = useKnowledgeLinkStore();
        await store.loadContext();
        expect(store.goals).toHaveLength(1);
        expect(store.programs).toHaveLength(1);
        expect(store.tasks).toHaveLength(1);
        expect(store.canvases).toHaveLength(1);
        expect(store.notes).toHaveLength(1);
    });

    it('loads milestones for a goal', async () => {
        vi.mocked(knowledgeLinkApi.milestones).mockResolvedValue({ milestones: [{ id: 3, goal_id: 1, title: 'M' }] });
        const store = useKnowledgeLinkStore();
        await store.loadMilestones(1);
        expect(store.milestones).toHaveLength(1);
    });

    it('creates a link and appends it', async () => {
        vi.mocked(knowledgeLinkApi.createForNote).mockResolvedValue({ link });
        const store = useKnowledgeLinkStore();
        const ok = await store.createLink(1, 'goal', 9, 'supports');
        expect(ok).toBe(true);
        expect(knowledgeLinkApi.createForNote).toHaveBeenCalledWith(1, {
            target_type: 'goal',
            target_id: 9,
            link_type: 'supports',
        });
        expect(store.links).toHaveLength(1);
    });

    it('surfaces an error on duplicate link (409)', async () => {
        vi.mocked(knowledgeLinkApi.createForNote).mockRejectedValue({
            code: 'CONFLICT',
            status: 409,
            message: 'duplicate',
            retryable: false,
        });
        const store = useKnowledgeLinkStore();
        const ok = await store.createLink(1, 'goal', 9, 'supports');
        expect(ok).toBe(false);
        expect(store.error?.status).toBe(409);
    });

    it('removes a link', async () => {
        vi.mocked(knowledgeLinkApi.removeFromNote).mockResolvedValue(undefined);
        vi.mocked(knowledgeLinkApi.linksForNote).mockResolvedValue({ links: [link, { ...link, id: 2 }] });
        const store = useKnowledgeLinkStore();
        await store.loadLinks(1);
        expect(store.links).toHaveLength(2);
        const ok = await store.removeLink(1, 1);
        expect(ok).toBe(true);
        expect(store.links).toHaveLength(1);
        expect(store.links[0].id).toBe(2);
    });

    it('loads links for a canvas', async () => {
        const canvasLink: KnowledgeLink = { ...link, source_type: 'canvas', source_id: 5, target_type: 'note' };
        vi.mocked(knowledgeLinkApi.linksForCanvas).mockResolvedValue({ links: [canvasLink] });
        const store = useKnowledgeLinkStore();
        await store.loadCanvasLinks(5);
        expect(store.links).toHaveLength(1);
        expect(store.links[0].source_type).toBe('canvas');
        expect(store.links[0].target_type).toBe('note');
    });

    it('creates a canvas link and appends it', async () => {
        const canvasLink: KnowledgeLink = { ...link, id: 7, source_type: 'canvas', source_id: 5, target_type: 'task' };
        vi.mocked(knowledgeLinkApi.createForCanvas).mockResolvedValue({ link: canvasLink });
        const store = useKnowledgeLinkStore();
        const ok = await store.createCanvasLink(5, 'task', 9, 'evidence_for');
        expect(ok).toBe(true);
        expect(knowledgeLinkApi.createForCanvas).toHaveBeenCalledWith(5, {
            target_type: 'task',
            target_id: 9,
            link_type: 'evidence_for',
        });
        expect(store.links).toHaveLength(1);
    });

    it('surfaces an error on duplicate canvas link (409)', async () => {
        vi.mocked(knowledgeLinkApi.createForCanvas).mockRejectedValue({
            code: 'CONFLICT',
            status: 409,
            message: 'duplicate',
            retryable: false,
        });
        const store = useKnowledgeLinkStore();
        const ok = await store.createCanvasLink(5, 'goal', 9, 'supports');
        expect(ok).toBe(false);
        expect(store.error?.status).toBe(409);
    });

    it('removes a canvas link', async () => {
        const canvasLink: KnowledgeLink = { ...link, id: 3, source_type: 'canvas', source_id: 5, target_type: 'goal' };
        vi.mocked(knowledgeLinkApi.removeFromCanvas).mockResolvedValue(undefined);
        vi.mocked(knowledgeLinkApi.linksForCanvas).mockResolvedValue({ links: [canvasLink] });
        const store = useKnowledgeLinkStore();
        await store.loadCanvasLinks(5);
        expect(store.links).toHaveLength(1);
        const ok = await store.removeCanvasLink(5, 3);
        expect(ok).toBe(true);
        expect(store.links).toHaveLength(0);
    });

    it('clears state', () => {
        const store = useKnowledgeLinkStore();
        store.goals = [{ id: 1, title: 'G' }];
        store.links = [link];
        store.clear();
        expect(store.goals).toHaveLength(0);
        expect(store.links).toHaveLength(0);
    });
});
