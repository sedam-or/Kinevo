import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import EditorHost from '../EditorHost.vue';
import type { EditorAdapter, EditorChange, EditorDocument, EditorTheme, Unsubscribe } from '../types';

function fakeAdapter(): EditorAdapter & { loaded: EditorDocument | null; readonly: boolean; theme: string } {
    const listeners = new Set<(change: EditorChange) => void>();
    const adapter = {
        loaded: null as EditorDocument | null,
        readonly: false,
        theme: 'light',
        load(document: EditorDocument | null): void {
            adapter.loaded = document;
        },
        getDocument(): EditorDocument {
            const doc: EditorDocument = { type: 'doc' };
            return doc;
        },
        getDerived(): { markdown: string; plainText: string } {
            return { markdown: '# hi', plainText: 'hi' };
        },
        save(baseVersion: number) {
            const doc: EditorDocument = { type: 'doc' };
            return { document: doc, derived: { markdown: '# hi', plainText: 'hi' }, baseVersion };
        },
        setReadOnly(enabled: boolean): void {
            adapter.readonly = enabled;
        },
        setTheme(theme: EditorTheme): void {
            adapter.theme = theme;
        },
        subscribe(listener: (change: EditorChange) => void): Unsubscribe {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        flush(): void {
            listeners.forEach((l) => l({ document: { type: 'doc' }, derived: { markdown: '', plainText: '' } }));
        },
        destroy(): void {
            listeners.clear();
        },
    };
    return adapter;
}

describe('EditorHost', () => {
    it('mounts the adapter and emits ready', async () => {
        const adapter = fakeAdapter();
        const factory = vi.fn(() => adapter);

        const wrapper = mount(EditorHost, {
            props: { document: { type: 'doc' }, adapterFactory: factory },
        });
        await flushPromises();

        expect(factory).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('ready')).toBeTruthy();
        expect(wrapper.find('[data-testid="editor-host"]').exists()).toBe(true);
    });

    it('loads the provided document into the adapter', async () => {
        const adapter = fakeAdapter();
        const doc: EditorDocument = { type: 'doc', content: [{ type: 'paragraph' }] };
        mount(EditorHost, {
            props: { document: doc, adapterFactory: () => adapter },
        });
        await flushPromises();
        expect(adapter.loaded).toStrictEqual(doc);
    });

    it('applies readOnly and theme to the adapter', async () => {
        const adapter = fakeAdapter();
        mount(EditorHost, {
            props: { document: null, readOnly: true, theme: 'dark', adapterFactory: () => adapter },
        });
        await flushPromises();
        expect(adapter.readonly).toBe(true);
        expect(adapter.theme).toBe('dark');
    });
});
