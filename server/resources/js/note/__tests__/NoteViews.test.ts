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
        runCommand(): void {},
        isCommandActive(): boolean {
            return false;
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

    it('explains the Knowledge surface contextually (P28-010)', async () => {
        vi.mocked(noteApi.list).mockResolvedValue({ notes: [] });
        const pinia = createPinia();
        setActivePinia(pinia);

        const wrapper = mount(NotesListView, { global: { plugins: [pinia] } });
        await flushPromises();

        // Header FeatureHelp exists with the registry's Knowledge explanation.
        const help = wrapper.find('[data-testid="feature-help-knowledge"]');
        expect(help.exists()).toBe(true);
        // Empty state names the surface, why it matters, and the next step.
        expect(wrapper.find('[data-testid="note-empty"]').text()).toContain('Nothing here yet');
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
            global: {
                plugins: [pinia],
                stubs: {
                    LinkManager: {
                        props: ['noteId'],
                        template: '<div data-testid="link-manager-stub">LinkManager</div>',
                    },
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="note-editor"]').exists()).toBe(true);
        // EditorHost is lazy (defineAsyncComponent, §89), so await its chunk.
        await vi.waitFor(() => {
            expect(wrapper.find('[data-testid="editor-host"]').exists()).toBe(true);
        });
        await wrapper.vm.$nextTick();
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
            global: {
                plugins: [pinia],
                stubs: {
                    LinkManager: {
                        props: ['noteId'],
                        template: '<div data-testid="link-manager-stub">LinkManager</div>',
                    },
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="link-manager-stub"]').exists()).toBe(true);
        await wrapper.find('[data-testid="note-title-input"]').setValue('Updated');
        await wrapper.find('[data-testid="note-save-now"]').trigger('click');
        await flushPromises();

        expect(noteApi.update).toHaveBeenCalled();
        expect(wrapper.find('[data-testid="note-save-state"]').text()).toContain('Saved');
    });
});

vi.mock('../../ai/api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../../ai/api')>();
    return {
        ...actual,
        aiApi: {
            ...actual.aiApi,
            config: vi.fn(),
            summarizeNote: vi.fn(),
            extractTasks: vi.fn(),
            acceptProposalWithResult: vi.fn(),
            rejectProposal: vi.fn(),
        },
    };
});

import { aiApi, type AiProposal } from '../../ai/api';

function aiReadyConfig(): unknown {
    return {
        config: {
            provider: 'ollama',
            enabled: true,
            model: 'llama3.1',
            base_url: 'http://localhost:11434',
            has_api_key: false,
            api_key_hint: null,
            status: { provider: 'ollama', model: 'llama3.1', available: true, latency_ms: 5, error: null, state: 'connected' },
            privacy_ok: true,
        },
    };
}

const summaryProposal: AiProposal = {
    id: 21,
    user_id: 1,
    proposal_type: 'summary',
    schema_version: 1,
    payload: { type: 'summary_proposal', summary: 'Research plan for Q3.', key_points: ['interview users', 'read papers'] },
    decision: 'pending',
    operation_id: null,
    created_at: '2026-08-24T00:00:00Z',
};

const extractionProposal: AiProposal = {
    id: 22,
    user_id: 1,
    proposal_type: 'task_extraction',
    schema_version: 1,
    payload: { type: 'task_extraction_proposal', tasks: [{ title: 'Interview users', estimated_minutes: 60 }, { title: 'Read papers' }] },
    decision: 'pending',
    operation_id: null,
    created_at: '2026-08-24T00:00:00Z',
};

describe('NoteEditView AI (TASK-P17-029)', () => {
    function mountEditor() {
        vi.mocked(noteApi.show).mockResolvedValue({ note });
        vi.mocked(noteApi.links).mockResolvedValue({ links: [] });
        const pinia = createPinia();
        setActivePinia(pinia);
        return mount(NoteEditView, {
            props: { noteId: 1, adapterFactory: adapterFactory() },
            global: {
                plugins: [pinia],
                stubs: {
                    LinkManager: { props: ['noteId'], template: '<div data-testid="link-manager-stub">LinkManager</div>' },
                },
            },
        });
    }

    it('routes to Settings when AI is not configured instead of generating (P17-028 gate)', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({ config: { status: { state: 'not_configured' } } } as never);
        const wrapper = mountEditor();
        await flushPromises();

        await wrapper.find('[data-testid="note-ai-summarize"]').trigger('click');
        await flushPromises();

        expect(aiApi.summarizeNote).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="note-ai-error"]').exists()).toBe(false);
    });

    it('summarizes in place — summary text plus key points', async () => {
        vi.mocked(aiApi.config).mockResolvedValue(aiReadyConfig() as never);
        vi.mocked(aiApi.summarizeNote).mockResolvedValue({ proposal: summaryProposal });
        const wrapper = mountEditor();
        await flushPromises();

        await wrapper.find('[data-testid="note-ai-summarize"]').trigger('click');
        await flushPromises();

        expect(aiApi.summarizeNote).toHaveBeenCalledWith(1);
        expect(wrapper.find('[data-testid="note-ai-summary"]').text()).toContain('Research plan for Q3.');
        expect(wrapper.find('[data-testid="note-ai-summary-points"]').text()).toContain('interview users');
    });

    it('extracts tasks and adds them only after acceptance (FR-62)', async () => {
        vi.mocked(aiApi.config).mockResolvedValue(aiReadyConfig() as never);
        vi.mocked(aiApi.extractTasks).mockResolvedValue({ proposal: extractionProposal });
        vi.mocked(aiApi.acceptProposalWithResult).mockResolvedValue({ tasks: [{ id: 5, title: 'Interview users' }, { id: 6, title: 'Read papers' }] });
        const wrapper = mountEditor();
        await flushPromises();

        await wrapper.find('[data-testid="note-ai-extract"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="note-ai-extraction-tasks"]').text()).toContain('Interview users');
        // No task exists before acceptance.
        expect(wrapper.find('[data-testid="note-ai-extract-done"]').exists()).toBe(false);

        await wrapper.find('[data-testid="note-ai-extract-accept"]').trigger('click');
        await flushPromises();

        expect(aiApi.acceptProposalWithResult).toHaveBeenCalledWith(22);
        expect(wrapper.find('[data-testid="note-ai-extract-done"]').text()).toContain('2 tasks added');
        expect(wrapper.find('[data-testid="note-ai-extraction-proposal"]').exists()).toBe(false);
    });

    it('rejecting the extraction posts reject and dismisses the panel', async () => {
        vi.mocked(aiApi.config).mockResolvedValue(aiReadyConfig() as never);
        vi.mocked(aiApi.extractTasks).mockResolvedValue({ proposal: extractionProposal });
        vi.mocked(aiApi.rejectProposal).mockResolvedValue(undefined);
        const wrapper = mountEditor();
        await flushPromises();

        await wrapper.find('[data-testid="note-ai-extract"]').trigger('click');
        await flushPromises();
        await wrapper.find('[data-testid="note-ai-extract-reject"]').trigger('click');
        await flushPromises();

        expect(aiApi.rejectProposal).toHaveBeenCalledWith(22);
        expect(wrapper.find('[data-testid="note-ai-extraction-proposal"]').exists()).toBe(false);
    });
});
