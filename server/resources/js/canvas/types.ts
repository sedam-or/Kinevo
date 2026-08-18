/**
 * Framework-agnostic canvas adapter contract.
 *
 * Excalidraw owns visual editing and canvas interaction mechanics. Kinevo
 * owns canvas identity, domain links, persistence, versioning, offline sync,
 * and permissions (ADR-005, architecture.md "Canvas boundary"). This
 * interface is the boundary any replaceable canvas engine must implement —
 * the Vue layer only ever talks to this contract, never to Excalidraw or
 * React directly.
 *
 * The canonical scene representation is Excalidraw scene JSON (elements +
 * appState + files), stored by Kinevo as `scene_json` in `canvas_documents`
 * (SRS §7.5).
 */

/** A single Excalidraw scene element (structural subset; engine-specific). */
export interface CanvasElement {
    id: string;
    type: string;
    [key: string]: unknown;
}

/** Canonical scene payload persisted as `canvas_documents.scene_json`. */
export interface CanvasScene {
    elements: CanvasElement[];
    appState: Record<string, unknown>;
    files?: Record<string, unknown>;
}

/** Canvas metadata owned by Kinevo (mirrors `canvases` row). */
export interface CanvasMeta {
    id: number;
    title: string;
    goalId?: number | null;
    milestoneId?: number | null;
    programId?: number | null;
    taskId?: number | null;
    version: number;
}

/** Document payload returned by save/load (mirrors `canvas_documents` row). */
export interface CanvasDocument {
    id: number;
    canvasId: number;
    schemaVersion: number;
    scene: CanvasScene;
    version: number;
}

/** Result of a save operation — caller persists it against baseVersion. */
export interface CanvasSaveResult {
    scene: CanvasScene;
    baseVersion: number;
}

export type CanvasTheme = 'light' | 'dark' | 'auto';

export type Unsubscribe = () => void;

/** Canvas change notification payload. */
export interface CanvasChange {
    scene: CanvasScene;
}

/**
 * Application-level canvas contract. Implementations MUST keep the canonical
 * scene JSON authoritative and MUST NOT store Kinevo business state (identity,
 * version, links) inside the engine.
 */
export interface CanvasAdapter {
    /** Mount the canvas engine into the host element. */
    mount(host: HTMLElement): void;

    /** Load a scene into the engine (null clears the canvas). */
    load(scene: CanvasScene | null): void;

    /** Return the current canonical scene JSON. */
    getScene(): CanvasScene;

    /**
     * Snapshot current state against a client-supplied base version so the
     * caller can persist with optimistic versioning.
     */
    save(baseVersion: number): CanvasSaveResult;

    /** Enable/disable user editing. */
    setReadOnly(enabled: boolean): void;

    /** Set the canvas theme. */
    setTheme(theme: CanvasTheme): void;

    /** Subscribe to scene changes. Returns an unsubscribe function. */
    subscribe(listener: (change: CanvasChange) => void): Unsubscribe;

    /** Force pending internal state to flush. */
    flush(): void;

    /** Destroy the engine and release resources. */
    destroy(): void;
}
