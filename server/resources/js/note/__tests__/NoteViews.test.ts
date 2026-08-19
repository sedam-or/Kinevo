import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

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

import NotesListView from '../NotesListView.vue';
import NoteEditView from '../NoteEditView.vue';
import { noteApi } from '../api';
import type { Note } from '../types';
import type { EditorAdapter, EditorChange, EditorDocument, EditorTheme, Unsubscribe } from '../../editor/types';

function fakeAdapter(): EditorAdapter {
    const listeners = new Set<(change: EditorChange) => void>();
    return {
        load(): void {},
        getDocument(): EditorDocument {
            const doc: EditorDocument = { type: 'doc' };
            return doc;
        },
        getDerived(): { markdown: string; plainText: string } {
            return { markdown: '# hi', plainText: 'body text' };
        },
        save(baseVersion: number) {
            const doc: EditorDocument = { type: 'doc' };
            return { document: doc, derived: { markdown: '# hi', plainText: 'body text' }, baseVersion };
        },
        setReadOnly(): void {},
        setTheme(_theme: EditorTheme): void {},
        subscribe(listener: (change: EditorChange) => void): Unsubscribe {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        flush(): void {},
        destroy(): void {},
    };
}

function adapterFactory(): (element: HTMLElement) => EditorAdapter {
    return () => fakeAdapter();
}

const note: Note = {
    id: 1,
    user_id: 1,
    title: 'Research',
    document_json: null,
    markdown_cache: null,
    plain_text_cache: 'body text',
    version: 3,
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

describe('NotesListView', () => {
    it('loads and lists notes', async () => {
        vi.mocked(noteApi.list).mockResolvedValue({ notes: [note] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(NotesListView, { global: { plugins: [pinia] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="note-item"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="note-item"]').text()).toContain('Research');
    });

    it('emits select when a note is opened', async () => {
        vi.mocked(noteApi.list).mockResolvedValue({ notes: [note] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(NotesListView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="note-open"]').trigger('click');
        expect(wrapper.emitted('select')?.[0]).toEqual([1]);
    });

    it('searches when the query changes', async () => {
        vi.mocked(noteApi.list).mockResolvedValue({ notes: [] });
        vi.mocked(noteApi.search).mockResolvedValue({ notes: [note], query: 'res' });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(NotesListView, { global: { plugins: [pinia] } });
        await flushPromises();

        await wrapper.find('[data-testid="note-search-input"]').setValue('res');
        await flushPromises();

        expect(noteApi.search).toHaveBeenCalledWith('res');
        expect(wrapper.find('[data-testid="note-item"]').exists()).toBe(true);
    });
});

describe('NoteEditView', () => {
    it('loads a note and shows editor + save state', async () => {
        vi.mocked(noteApi.show).mockResolvedValue({ note });
        vi.mocked(noteApi.links).mockResolvedValue({ links: [] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(NoteEditView, {
            props: { noteId: 1, adapterFactory: adapterFactory() },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="note-editor"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="editor-host"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="note-save-state"]').exists()).toBe(true);
    });

    it('saves and shows linked entities', async () => {
        vi.mocked(noteApi.show).mockResolvedValue({ note });
        vi.mocked(noteApi.links).mockResolvedValue({
            links: [{ id: 1, user_id: 1, source_type: 'note', source_id: 1, target_type: 'goal', target_id: 9, link_type: 'supports' }],
        });
        vi.mocked(noteApi.update).mockResolvedValue({ note: { ...note, title: 'Updated', version: 4 } });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(NoteEditView, {
            props: { noteId: 1, adapterFactory: adapterFactory() },
            global: { plugins: [pinia] },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="note-link-item"]').text()).toContain('goal');
        await wrapper.find('[data-testid="note-title-input"]').setValue('Updated');
        await wrapper.find('[data-testid="note-save-now"]').trigger('click');
        await flushPromises();

        expect(noteApi.update).toHaveBeenCalled();
        expect(wrapper.find('[data-testid="note-save-state"]').text()).toContain('Saved');
    });
});
