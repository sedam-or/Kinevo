import { createRoot, type Root } from 'react-dom/client';
import { createElement, type RefObject } from 'react';
import { ExcalidrawIsland, type ExcalidrawIslandHandle } from './react/ExcalidrawIsland';
import type {
    CanvasAdapter,
    CanvasChange,
    CanvasSaveResult,
    CanvasScene,
    CanvasTheme,
    Unsubscribe,
} from './types';

const EMPTY_APP_STATE: Record<string, unknown> = {};

/**
 * Excalidraw canvas adapter behind the Kinevo CanvasAdapter boundary.
 *
 * Vue talks to this adapter; this adapter owns the React island lifecycle.
 * Kinevo owns scene persistence/versioning; Excalidraw owns visual editing.
 * The engine can be replaced without touching the Vue layer.
 */
export class ExcalidrawCanvasAdapter implements CanvasAdapter {
    private root: Root | null = null;
    private handle: RefObject<ExcalidrawIslandHandle | null> = { current: null };
    private listeners = new Set<(change: CanvasChange) => void>();
    private scene: CanvasScene | null = null;
    private readOnly = false;
    private theme: CanvasTheme = 'auto';
    private host: HTMLElement | null = null;

    mount(host: HTMLElement): void {
        this.host = host;
        this.root = createRoot(host);
        this.renderIsland();
    }

    load(scene: CanvasScene | null): void {
        this.scene = scene;
        this.handle.current?.updateScene(scene);
    }

    getScene(): CanvasScene {
        const live = this.handle.current?.getScene();
        if (live !== null && live !== undefined) {
            return live;
        }
        return this.scene ?? { elements: [], appState: EMPTY_APP_STATE };
    }

    save(baseVersion: number): CanvasSaveResult {
        return {
            scene: this.getScene(),
            baseVersion,
        };
    }

    setReadOnly(enabled: boolean): void {
        this.readOnly = enabled;
        this.handle.current?.setReadOnly(enabled);
    }

    setTheme(theme: CanvasTheme): void {
        this.theme = theme;
        this.handle.current?.setTheme(theme);
    }

    subscribe(listener: (change: CanvasChange) => void): Unsubscribe {
        this.listeners.add(listener);
        return () => {
            this.listeners.delete(listener);
        };
    }

    flush(): void {
        this.handle.current?.flush();
    }

    destroy(): void {
        this.handle.current?.flush();
        this.listeners.clear();
        this.handle.current = null;
        if (this.root !== null) {
            this.root.unmount();
            this.root = null;
        }
        if (this.host !== null) {
            this.host.replaceChildren();
            this.host = null;
        }
    }

    private renderIsland(): void {
        const initialReadOnly = this.readOnly;
        const initialTheme = this.theme;
        const initialScene = this.scene;

        const island = createElement(
            ExcalidrawIsland,
            {
                ref: this.handle,
                initialScene,
                initialReadOnly,
                initialTheme,
                onSceneChange: (scene: CanvasScene) => {
                    this.scene = scene;
                    for (const listener of this.listeners) {
                        listener({ scene });
                    }
                },
            },
        );

        this.root?.render(island);
    }
}
