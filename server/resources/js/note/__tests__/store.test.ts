import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        noteApi: {
            list: vi.fn(),
            show: vi.fn(),
            create: vi.fn(),
            update: vi.fn(),
            search: vi.fn(),
            links: vi.fn(),
        },
    };
});

import { useNoteStore } from '../store';
import { noteApi } from '../api';
import type { Note } from '../types';

const note: Note = {
    id: 1,
    user_id: 1,
    title: 'Research',
    document_json: null,
    markdown_cache: null,
    plain_text_cache: 'body',
    version: 3,
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('note store', () => {
    it('loads the note list', async () => {
        vi.mocked(noteApi.list).mockResolvedValue({ notes: [note] });
        const store = useNoteStore();
        await store.loadList();
        expect(store.notes).toHaveLength(1);
        expect(store.notes[0].title).toBe('Research');
    });

    it('searches notes', async () => {
        vi.mocked(noteApi.search).mockResolvedValue({ notes: [note], query: 'res' });
        const store = useNoteStore();
        await store.search('res');
        expect(store.searchResults).toHaveLength(1);
    });

    it('loads a note with its links', async () => {
        vi.mocked(noteApi.show).mockResolvedValue({ note });
        vi.mocked(noteApi.links).mockResolvedValue({ links: [{ id: 1, user_id: 1, source_type: 'note', source_id: 1, target_type: 'goal', target_id: 9, link_type: 'supports' }] });
        const store = useNoteStore();
        await store.load(1);
        expect(store.current?.id).toBe(1);
        expect(store.links).toHaveLength(1);
    });

    it('creates a note and prepends it', async () => {
        vi.mocked(noteApi.create).mockResolvedValue({ note });
        const store = useNoteStore();
        const created = await store.create('Research');
        expect(created?.id).toBe(1);
        expect(store.notes).toHaveLength(1);
    });

    it('saves with the current base version and sets saved state', async () => {
        vi.mocked(noteApi.show).mockResolvedValue({ note });
        vi.mocked(noteApi.links).mockResolvedValue({ links: [] });
        vi.mocked(noteApi.update).mockResolvedValue({ note: { ...note, title: 'Renamed', version: 4 } });
        const store = useNoteStore();
        await store.load(1);
        await store.save({ title: 'Renamed', plain_text_cache: 'body' });
        expect(noteApi.update).toHaveBeenCalledWith(1, expect.objectContaining({ base_version: 3 }));
        expect(store.saveState).toBe('saved');
        expect(store.current?.version).toBe(4);
    });

    it('sets conflict state on a 409 save', async () => {
        vi.mocked(noteApi.show).mockResolvedValue({ note });
        vi.mocked(noteApi.links).mockResolvedValue({ links: [] });
        vi.mocked(noteApi.update).mockRejectedValue({ code: 'CONFLICT', status: 409, message: 'version conflict', retryable: false });
        const store = useNoteStore();
        await store.load(1);
        await store.save({ title: 'X', plain_text_cache: 'body' });
        expect(store.saveState).toBe('conflict');
    });
});
