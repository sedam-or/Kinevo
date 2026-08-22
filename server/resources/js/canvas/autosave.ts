/**
 * Canvas autosave/versioning domain (TASK-043, design.md §Canvas save states,
 * FR-56). This module is framework-agnostic: it orchestrates the CanvasAdapter
 * and a server save function, tracking optimistic versioning and surfacing
 * save state for the UI. It never touches React/Excalidraw directly.
 */
import type { CanvasAdapter, CanvasScene, Unsubscribe } from './types';

/** Visible save lifecycle states (design.md §Canvas save states). */
export type CanvasSaveState =
    | 'idle' // no unsaved changes
    | 'dirty' // edits pending autosave
    | 'saving' // save in flight
    | 'saved' // last save succeeded
    | 'offline' // network unavailable
    | 'conflict' // stale base version -> user must reconcile
    | 'failed'; // non-conflict, non-offline failure

/** Result of a server canvas save request. */
export interface CanvasSaveResponse {
    /** Server-issued version after a successful save. */
    version: number;
}

/**
 * Contract for persisting a canvas scene. Implemented by the HTTP layer
 * (e.g. `PUT /api/v1/canvases/{canvasId}`). Injectable so the controller is
 * testable in isolation.
 */
export interface CanvasPersistence {
    save(canvasId: number, baseVersion: number, scene: CanvasScene): Promise<CanvasSaveResponse>;
}

/** A cancellable scheduled call. */
export interface ScheduledCall {
    (): void;
    cancel(): void;
}

/** Optional factory producing a debounced invocation; injectable for tests. */
export type DebounceFn = <A extends unknown[]>(
    fn: (...args: A) => void,
    wait: number,
) => ScheduledCall;

/** Notification payload for state transitions. */
export interface CanvasSaveStateChange {
    state: CanvasSaveState;
    error?: string;
    version?: number;
}

/**
 * Autosave controller wrapping a CanvasAdapter and a CanvasPersistence.
 *
 * Responsibilities:
 * - subscribe to adapter scene changes and mark state dirty;
 * - debounce saves (configurable wait);
 * - send the current scene with the tracked base version (optimistic locking,
 *   FR-56);
 * - on success, advance the base version from the server response;
 * - on 409-style conflict, surface a `conflict` state and pause autosave until
 *   the caller reconciles (reload + reset);
 * - on network failure, surface `offline`/`failed` without discarding the
 *   pending scene.
 */
export class CanvasAutosaveController {
    private readonly adapter: CanvasAdapter;
    private readonly persistence: CanvasPersistence;
    private readonly canvasId: number;
    private readonly debounceWait: number;
    private readonly debounce: DebounceFn;

    private baseVersion: number;
    private state: CanvasSaveState = 'idle';
    private unsavedScene: CanvasScene | null = null;
    private disposed = false;
    private readonly listeners = new Set<(change: CanvasSaveStateChange) => void>();
    private readonly unsubscribeFromAdapter: Unsubscribe;
    private pendingSave: ScheduledCall | null = null;
    private saveInFlight: Promise<void> = Promise.resolve();

    constructor(
        adapter: CanvasAdapter,
        persistence: CanvasPersistence,
        canvasId: number,
        initialBaseVersion = 0,
        debounceWait = 800,
        debounce: DebounceFn = defaultDebounce,
    ) {
        this.adapter = adapter;
        this.persistence = persistence;
        this.canvasId = canvasId;
        this.baseVersion = initialBaseVersion;
        this.debounceWait = debounceWait;
        this.debounce = debounce;

        this.unsubscribeFromAdapter = adapter.subscribe((change) => {
            this.unsavedScene = change.scene;
            this.markDirty();
        });
    }

    /** Current save state. */
    getState(): CanvasSaveState {
        return this.state;
    }

    /** Current optimistic base version. */
    getBaseVersion(): number {
        return this.baseVersion;
    }

    /** Subscribe to save-state changes. Returns an unsubscribe function. */
    subscribe(listener: (change: CanvasSaveStateChange) => void): Unsubscribe {
        this.listeners.add(listener);
        return () => {
            this.listeners.delete(listener);
        };
    }

    /** Schedule an immediate autosave (e.g. on flush/blur). */
    flush(): Promise<void> {
        this.cancelDebounce();
        return this.runSave();
    }

    /**
     * Reconcile after a conflict: adopt a new authoritative base version and
     * scene, returning to a clean state. The caller supplies the resolved
     * scene (server copy or user merge) and the server version.
     */
    reconcile(scene: CanvasScene, serverVersion: number): void {
        this.unsavedScene = scene;
        this.baseVersion = serverVersion;
        this.adapter.load(scene);
        this.setState('idle');
    }

    /** Force a manual save immediately, bypassing debounce. */
    saveNow(): Promise<void> {
        this.cancelDebounce();
        return this.runSave();
    }

    /** Release subscriptions and stop autosave. */
    dispose(): void {
        this.disposed = true;
        this.cancelDebounce();
        this.unsubscribeFromAdapter();
        this.listeners.clear();
    }

    private markDirty(): void {
        if (this.disposed) {
            return;
        }
        if (this.state === 'saving' || this.state === 'conflict' || this.state === 'failed') {
            // Already saving, or paused pending reconciliation — do not enqueue.
            return;
        }
        this.setState('dirty');
        this.scheduleSave();
    }

    private scheduleSave(): void {
        // Fixed-window trailing debounce: if a save is already scheduled, let
        // it run — `unsavedScene` always holds the newest scene, so the save
        // is never stale. Re-arming the timer on every change would let a
        // continuous change stream starve autosave forever.
        if (this.pendingSave !== null) {
            return;
        }
        this.pendingSave = this.debounce(() => {
            this.pendingSave = null;
            this.runSave();
        }, this.debounceWait);
        this.pendingSave();
    }

    private cancelDebounce(): void {
        if (this.pendingSave !== null) {
            this.pendingSave.cancel();
            this.pendingSave = null;
        }
    }

    private setState(state: CanvasSaveState, error?: string, version?: number): void {
        this.state = state;
        const payload: CanvasSaveStateChange = { state };
        if (error !== undefined) {
            payload.error = error;
        }
        if (version !== undefined) {
            payload.version = version;
        }
        for (const listener of this.listeners) {
            listener(payload);
        }
    }

    private runSave(): Promise<void> {
        if (this.disposed) {
            return Promise.resolve();
        }
        const scene = this.unsavedScene;
        if (scene === null) {
            this.setState('idle');
            return Promise.resolve();
        }
        if (this.state === 'conflict') {
            return Promise.resolve();
        }

        const baseVersion = this.baseVersion;
        this.setState('saving');

        this.saveInFlight = this.persistence
            .save(this.canvasId, baseVersion, scene)
            .then((response) => {
                this.baseVersion = response.version;
                this.unsavedScene = null;
                this.setState('saved', undefined, response.version);
            })
            .catch((error: unknown) => {
                const code = (error as { code?: string })?.code;
                if (code === 'CANVAS_VERSION_CONFLICT') {
                    this.setState('conflict', String(error));
                    return;
                }
                if (code === 'OFFLINE') {
                    this.setState('offline', String(error));
                    return;
                }
                this.setState('failed', String(error));
            });

        return this.saveInFlight;
    }
}

function defaultDebounce<A extends unknown[]>(fn: (...args: A) => void, wait: number): ScheduledCall {
    let timer: ReturnType<typeof setTimeout> | undefined;
    let lastArgs: A | undefined;

    const scheduled = ((...args: A) => {
        lastArgs = args;
        if (timer !== undefined) {
            clearTimeout(timer);
        }
        timer = setTimeout(() => {
            timer = undefined;
            if (lastArgs !== undefined) {
                fn(...lastArgs);
            }
        }, wait);
    }) as ScheduledCall;

    scheduled.cancel = () => {
        if (timer !== undefined) {
            clearTimeout(timer);
            timer = undefined;
        }
        lastArgs = undefined;
    };

    return scheduled;
}
