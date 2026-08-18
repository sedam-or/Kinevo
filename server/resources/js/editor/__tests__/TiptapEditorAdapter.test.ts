import { beforeEach, describe, expect, it } from 'vitest';
import { TiptapEditorAdapter } from '../TiptapEditorAdapter';
import type { EditorDocument } from '../types';

function sampleDoc(): EditorDocument {
    return {
        type: 'doc',
        content: [
            { type: 'paragraph', content: [{ type: 'text', text: 'Hello adapter' }] },
        ],
    };
}

function changedDoc(): EditorDocument {
    return {
        type: 'doc',
        content: [
            { type: 'paragraph', content: [{ type: 'text', text: 'Changed' }] },
        ],
    };
}

describe('TiptapEditorAdapter', () => {
    let element: HTMLElement;

    beforeEach(() => {
        element = document.createElement('div');
        document.body.appendChild(element);
    });

    it('loads and returns a canonical document', () => {
        const adapter = new TiptapEditorAdapter({ element });

        adapter.load(sampleDoc());

        expect(adapter.getDocument().type).toBe('doc');
        expect(adapter.getDocument().content?.[0]?.type).toBe('paragraph');
        expect(
            adapter.getDocument().content?.[0]?.content?.[0],
        ).toMatchObject({ type: 'text', text: 'Hello adapter' });

        adapter.destroy();
    });

    it('derives markdown and plain text from the loaded document', () => {
        const adapter = new TiptapEditorAdapter({ element });
        adapter.load(sampleDoc());

        const derived = adapter.getDerived();
        expect(derived.markdown).toContain('Hello adapter');
        expect(derived.plainText).toContain('Hello adapter');

        adapter.destroy();
    });

    it('save returns the document with the supplied base version', () => {
        const adapter = new TiptapEditorAdapter({ element });
        adapter.load(sampleDoc());

        const result = adapter.save(7);
        expect(result.baseVersion).toBe(7);
        expect(result.document.type).toBe('doc');
        expect(result.derived.plainText).toContain('Hello adapter');
        expect(adapter.currentBaseVersion).toBe(7);

        adapter.destroy();
    });

    it('notifies subscribers on content updates', () => {
        const adapter = new TiptapEditorAdapter({ element });
        let lastChange: Parameters<Parameters<typeof adapter.subscribe>[0]>[0] | undefined;

        adapter.subscribe((change) => {
            lastChange = change;
        });

        adapter.load(changedDoc());

        expect(lastChange).toBeDefined();
        expect(lastChange?.document.content?.[0]?.content?.[0]).toMatchObject({
            type: 'text',
            text: 'Changed',
        });

        adapter.destroy();
    });

    it('unsubscribe stops notifications', () => {
        const adapter = new TiptapEditorAdapter({ element });
        let notified = 0;

        const unsubscribe = adapter.subscribe(() => {
            notified += 1;
        });

        adapter.load(changedDoc());
        expect(notified).toBe(1);

        unsubscribe();
        adapter.flush();
        expect(notified).toBe(1);

        adapter.destroy();
    });

    it('setReadOnly toggles editability', () => {
        const adapter = new TiptapEditorAdapter({ element });

        adapter.setReadOnly(true);
        expect(adapter.isEditable).toBe(false);

        adapter.setReadOnly(false);
        expect(adapter.isEditable).toBe(true);

        adapter.destroy();
    });

    it('setTheme updates the theme attribute', () => {
        const adapter = new TiptapEditorAdapter({ element });

        adapter.setTheme('dark');
        expect(adapter.themeAttribute).toBe('dark');

        adapter.setTheme('light');
        expect(adapter.themeAttribute).toBe('light');

        adapter.destroy();
    });

    it('load(null) clears content to the canonical empty document', () => {
        const adapter = new TiptapEditorAdapter({ element });
        adapter.load(sampleDoc());
        expect(adapter.getDocument().content?.[0]?.content?.[0]?.text).toBe('Hello adapter');

        adapter.load(null);
        const cleared = adapter.getDocument();
        expect(cleared.content?.length).toBe(1);
        expect(cleared.content?.[0]?.type).toBe('paragraph');
        expect(cleared.content?.[0]?.content ?? []).toHaveLength(0);

        adapter.destroy();
    });

    it('destroy releases the editor and listeners', () => {
        const adapter = new TiptapEditorAdapter({ element });
        let notified = 0;

        adapter.subscribe(() => {
            notified += 1;
        });
        adapter.destroy();
        adapter.flush();
        expect(notified).toBe(0);
    });
});