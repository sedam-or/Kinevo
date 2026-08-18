/**
 * Framework-agnostic editor adapter contract.
 *
 * Tiptap owns document editing mechanics. Kinevo owns note identity,
 * persistence, versioning, access, and business semantics (SRS §10.1,
 * architecture.md "Knowledge boundary"). This interface is the boundary
 * that any replaceable editor engine must implement.
 *
 * Canonical representation is ProseMirror/Tiptap structured JSON
 * (SRS §10.2). Markdown and plain text are derived formats for
 * interoperability/search.
 */

/** A single ProseMirror/Tiptap mark. */
export interface EditorMark {
    type: string;
    attrs?: Record<string, unknown>;
}

/** A single ProseMirror/Tiptap node. */
export interface EditorNode {
    type: string;
    attrs?: Record<string, unknown>;
    content?: EditorNode[];
    marks?: EditorMark[];
    text?: string;
}

/** Canonical editor document (ProseMirror/Tiptap JSON). */
export interface EditorDocument {
    type: 'doc';
    content?: EditorNode[];
}

/** Derived, non-canonical representations of a document. */
export interface DerivedDocument {
    markdown: string;
    plainText: string;
}

/** Result of a save/export request on the adapter. */
export interface EditorSaveResult {
    document: EditorDocument;
    derived: DerivedDocument;
    baseVersion: number;
}

/** Editor change notification payload. */
export interface EditorChange {
    document: EditorDocument;
    derived: DerivedDocument;
}

export type EditorTheme = 'light' | 'dark' | 'auto';

export type Unsubscribe = () => void;

/**
 * Application-level editor contract. Implementations MUST keep the canonical
 * document JSON authoritative and MUST NOT store Kinevo business state
 * inside the editor engine.
 */
export interface EditorAdapter {
    /** Load a canonical document into the editor (null clears content). */
    load(document: EditorDocument | null): void;

    /** Return the current canonical document JSON. */
    getDocument(): EditorDocument;

    /** Derive markdown + plain text from the current document. */
    getDerived(): DerivedDocument;

    /**
     * Snapshot current state against a client-supplied base version. Returns
     * the canonical document plus derived formats; the caller persists it.
     */
    save(baseVersion: number): EditorSaveResult;

    /** Enable/disable user editing. */
    setReadOnly(enabled: boolean): void;

    /** Set the editor theme. */
    setTheme(theme: EditorTheme): void;

    /** Subscribe to content changes. Returns an unsubscribe function. */
    subscribe(listener: (change: EditorChange) => void): Unsubscribe;

    /** Force any pending internal state to be flushed. */
    flush(): void;

    /** Destroy the editor and release resources. */
    destroy(): void;
}