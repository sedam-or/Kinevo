import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        canvasApi: {
            list: vi.fn(),
            show: vi.fn(),
            create: vi.fn(),
            save: vi.fn(),
            rename: vi.fn(),
            archive: vi.fn(),
        },
    };
});

import { useCanvasStore } from '../store';
import { canvasApi } from '../api';
import type { CanvasDto } from '../api-types';

const canvasRow: CanvasDto = {
    id: 3,
    user_id: 1,
    title: 'Research board',
    goal_id: null,
    milestone_id: null,
    program_id: null,
    task_id: null,
    version: 2,
    archived_at: null,
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('canvas store', () => {
    it('loads the canvas list', async () => {
        vi.mocked(canvasApi.list).mockResolvedValue({ canvases: [canvasRow] });
        const store = useCanvasStore();
        await store.loadList();

        expect(store.canvases).toHaveLength(1);
        expect(store.canvases[0].title).toBe('Research board');
        expect(store.loading).toBe(false);
    });

    it('records a list error', async () => {
        vi.mocked(canvasApi.list).mockRejectedValue({ status: 500, message: 'boom', retryable: true });
        const store = useCanvasStore();
        await store.loadList();

        expect(store.error?.message).toBe('boom');
        expect(store.canvases).toHaveLength(0);
    });

    it('opens a canvas with its document', async () => {
        vi.mocked(canvasApi.show).mockResolvedValue({
            canvas: canvasRow,
            document: { id: 1, canvas_id: 3, schema_version: 1, scene_json: { elements: [] }, version: 2 },
        });
        const store = useCanvasStore();
        await store.open(3);

        expect(store.current?.title).toBe('Research board');
        expect(store.document).toEqual({ elements: [] });
        expect(store.documentVersion).toBe(2);
        expect(store.saveState).toBe('saved');
    });

    it('opens a canvas without a document as version 0', async () => {
        vi.mocked(canvasApi.show).mockResolvedValue({ canvas: canvasRow, document: null });
        const store = useCanvasStore();
        await store.open(3);

        expect(store.document).toBeNull();
        expect(store.documentVersion).toBe(0);
    });

    it('creates a canvas and prepends it to the list', async () => {
        vi.mocked(canvasApi.create).mockResolvedValue({ canvas: canvasRow });
        const store = useCanvasStore();
        const created = await store.create('Research board');

        expect(created?.id).toBe(3);
        expect(store.canvases[0].id).toBe(3);
    });

    it('renames the current canvas and syncs the list', async () => {
        vi.mocked(canvasApi.rename).mockResolvedValue({
            canvas: { ...canvasRow, title: 'Renamed' },
        });
        const store = useCanvasStore();
        store.canvases = [canvasRow];
        store.current = canvasRow;

        const ok = await store.rename('Renamed');

        expect(ok).toBe(true);
        expect(store.current?.title).toBe('Renamed');
        expect(store.canvases[0].title).toBe('Renamed');
    });

    it('archives the canvas and removes it from the list', async () => {
        vi.mocked(canvasApi.archive).mockResolvedValue({ canvas: { ...canvasRow, archived_at: '2026-08-19 10:00:00' } });
        const store = useCanvasStore();
        store.canvases = [canvasRow, { ...canvasRow, id: 9, title: 'Other' }];
        store.current = canvasRow;

        const ok = await store.archive();

        expect(ok).toBe(true);
        expect(store.current).toBeNull();
        expect(store.canvases.map((c) => c.id)).toEqual([9]);
    });
});