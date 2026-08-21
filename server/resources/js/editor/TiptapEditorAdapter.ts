/**
 * Tiptap implementation of the EditorAdapter boundary.
 *
 * Kinevo owns note identity, links, persistence, access, and business
 * semantics (architecture.md "Knowledge boundary"). This adapter only wraps
 * the editing mechanics of Tiptap behind the application-level contract.
 *
 * Extensions are deliberately bounded to the node set required by
 * docs/design.md (headings, lists, task list, links, code block, quote)
 * plus StarterKit baseline. Add extensions only when tied to requirements.
 */

import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import TaskItem from '@tiptap/extension-task-item';
import TaskList from '@tiptap/extension-task-list';
import type { Level } from '@tiptap/extension-heading';
import { derive } from './serializers';
import type {
    EditorAdapter,
    EditorChange,
    EditorDocument,
    EditorSaveResult,
    EditorTheme,
    EditorToolbarCommand,
    Unsubscribe,
} from './types';

export interface TiptapEditorAdapterOptions {
    element: HTMLElement;
    /** Content editor element selector for Tiptap mount. */
    contentSelector?: string;
    onSaveShortcut?: () => void;
}

/** Headings allowed by the bounded extension set (design.md). */
const headingLevels: Level[] = [1, 2, 3, 4, 5, 6];

export class TiptapEditorAdapter implements EditorAdapter {
    private readonly editor: Editor;

    private readonly listeners = new Set<(change: EditorChange) => void>();

    private baseVersion = 1;

    private readonly contentElement: HTMLElement;

    constructor(options: TiptapEditorAdapterOptions) {
        this.contentElement = options.contentSelector
            ? options.element.querySelector<HTMLElement>(options.contentSelector)
                  ?? options.element
            : options.element;

        const editorProps: Editor['options']['editorProps'] = {
            attributes: {
                class: 'kinevo-editor-prose',
                'data-editor-theme': 'light',
            },
            handleKeyDown: (_view, event) => {
                if ((event.metaKey || event.ctrlKey) && event.key === 's') {
                    event.preventDefault();
                    options.onSaveShortcut?.();
                    return true;
                }
                return false;
            },
        };

        this.editor = new Editor({
            element: this.contentElement,
            extensions: [
                StarterKit.configure({
                    heading: { levels: headingLevels },
                }),
                Link.configure({
                    openOnClick: false,
                    autolink: true,
                    defaultProtocol: 'https',
                }),
                TaskList,
                TaskItem.configure({ nested: true }),
            ],
            onUpdate: ({ editor }) => {
                this.emit(editor.getJSON() as EditorDocument);
            },
            editorProps,
        });
    }

    private get derived() {
        return derive(this.editor.getJSON() as EditorDocument);
    }

    private emit(document: EditorDocument): void {
        const change: EditorChange = { document, derived: derive(document) };
        for (const listener of this.listeners) {
            listener(change);
        }
    }

    load(document: EditorDocument | null): void {
        if (document === null) {
            this.editor.commands.setContent('');
            return;
        }
        this.editor.commands.setContent(document as never);
    }

    getDocument(): EditorDocument {
        return this.editor.getJSON() as EditorDocument;
    }

    getDerived() {
        return this.derived;
    }

    save(baseVersion: number): EditorSaveResult {
        this.baseVersion = baseVersion;
        return {
            document: this.getDocument(),
            derived: this.derived,
            baseVersion,
        };
    }

    get currentBaseVersion(): number {
        return this.baseVersion;
    }

    setReadOnly(enabled: boolean): void {
        this.editor.setEditable(!enabled);
    }

    /** Test/observability accessor: true when the editor accepts input. */
    get isEditable(): boolean {
        return this.editor.isEditable;
    }

    setTheme(theme: EditorTheme): void {
        this.editor.setOptions({
            editorProps: {
                attributes: {
                    class: 'kinevo-editor-prose',
                    'data-editor-theme': theme,
                },
            },
        });
    }

    /** Test/observability accessor: current theme attribute value. */
    get themeAttribute(): string {
        return this.contentElement
            .querySelector('.tiptap')
            ?.getAttribute('data-editor-theme')
            ?? '';
    }

    runCommand(command: EditorToolbarCommand): void {
        const chain = this.editor.chain().focus();
        switch (command.type) {
            case 'bold':
                chain.toggleBold();
                break;
            case 'italic':
                chain.toggleItalic();
                break;
            case 'heading':
                if (command.level === null) {
                    chain.setParagraph();
                } else {
                    chain.toggleHeading({ level: command.level as Level });
                }
                break;
            case 'bulletList':
                chain.toggleBulletList();
                break;
            case 'taskList':
                chain.toggleTaskList();
                break;
            case 'link':
                if (command.url === null) {
                    chain.unsetLink();
                } else {
                    chain.extendMarkRange('link').setLink({ href: command.url });
                }
                break;
        }
        chain.run();
    }

    isCommandActive(command: EditorToolbarCommand): boolean {
        switch (command.type) {
            case 'bold':
                return this.editor.isActive('bold');
            case 'italic':
                return this.editor.isActive('italic');
            case 'heading':
                if (command.level === null) {
                    return this.editor.isActive('paragraph');
                }
                return this.editor.isActive('heading', { level: command.level });
            case 'bulletList':
                return this.editor.isActive('bulletList');
            case 'taskList':
                return this.editor.isActive('taskList');
            case 'link':
                return this.editor.isActive('link');
        }
    }

    subscribe(listener: (change: EditorChange) => void): Unsubscribe {
        this.listeners.add(listener);
        return () => {
            this.listeners.delete(listener);
        };
    }

    flush(): void {
        this.emit(this.getDocument());
    }

    destroy(): void {
        this.listeners.clear();
        this.editor.destroy();
    }
}