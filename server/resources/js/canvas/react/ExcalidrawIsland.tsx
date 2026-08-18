import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';
import { Excalidraw } from '@excalidraw/excalidraw';
import type { CanvasScene } from '../types';

/**
 * React Island — the ONLY place React + Excalidraw are allowed to live
 * (ADR-005, ADR-002). Vue renders this component inside a bounded host and
 * communicates through the imperative handle; no Kinevo business state is
 * stored inside Excalidraw.
 *
 * The Excalidraw imperative API is surfaced through the `excalidrawAPI`
 * callback prop; we project only the subset we need onto our own handle so
 * the Vue layer never depends on Excalidraw's internal types.
 */

/** Minimal projection of the Excalidraw imperative API we consume. */
interface ExcalidrawApiProjection {
    updateScene(data: {
        elements?: readonly unknown[];
        appState?: Record<string, unknown>;
        files?: Record<string, unknown>;
    }): void;
    getSceneElements(): readonly unknown[];
    getAppState(): Record<string, unknown>;
    getFiles(): Record<string, unknown> | undefined;
}

export interface ExcalidrawIslandHandle {
    /** Push a scene into the engine. */
    updateScene(scene: CanvasScene | null): void;
    /** Read the current engine scene. */
    getScene(): CanvasScene;
    /** Enable/disable interaction. */
    setReadOnly(enabled: boolean): void;
    /** Set theme. */
    setTheme(theme: 'light' | 'dark' | 'auto'): void;
    /** Registers a change listener, returns unsubscribe. */
    subscribe(listener: (scene: CanvasScene) => void): () => void;
    /** Force a flush of the current scene through change listeners. */
    flush(): void;
}

interface ExcalidrawIslandProps {
    initialScene: CanvasScene | null;
    initialReadOnly: boolean;
    initialTheme: 'light' | 'dark' | 'auto';
    onSceneChange?: (scene: CanvasScene) => void;
}

function normalizeScene(
    elements: readonly unknown[],
    appState: Record<string, unknown>,
    files: Record<string, unknown> | undefined,
): CanvasScene {
    return {
        elements: elements as CanvasScene['elements'],
        appState,
        files: files ?? undefined,
    };
}

const EMPTY_APP_STATE: Record<string, unknown> = {};

export const ExcalidrawIsland = forwardRef<ExcalidrawIslandHandle, ExcalidrawIslandProps>(
    function ExcalidrawIsland(
        { initialScene, initialReadOnly, initialTheme, onSceneChange },
        ref,
    ) {
        const apiRef = useRef<ExcalidrawApiProjection | null>(null);
        const listenersRef = useRef<Set<(scene: CanvasScene) => void>>(new Set());
        const onSceneChangeRef = useRef(onSceneChange);
        const latestSceneRef = useRef<CanvasScene | null>(initialScene);

        useEffect(() => {
            onSceneChangeRef.current = onSceneChange;
        }, [onSceneChange]);

        const notify = (scene: CanvasScene): void => {
            latestSceneRef.current = scene;
            for (const listener of listenersRef.current) {
                listener(scene);
            }
            onSceneChangeRef.current?.(scene);
        };

        useImperativeHandle(
            ref,
            (): ExcalidrawIslandHandle => ({
                updateScene(scene) {
                    latestSceneRef.current = scene;
                    apiRef.current?.updateScene({
                        elements: scene?.elements ?? [],
                        appState: scene?.appState ?? EMPTY_APP_STATE,
                        files: scene?.files ?? undefined,
                    });
                },
                getScene() {
                    const api = apiRef.current;
                    if (api === null) {
                        return latestSceneRef.current ?? { elements: [], appState: EMPTY_APP_STATE };
                    }
                    return normalizeScene(api.getSceneElements(), api.getAppState(), api.getFiles());
                },
                setReadOnly(enabled) {
                    apiRef.current?.updateScene({
                        appState: { viewModeEnabled: enabled },
                    });
                },
                setTheme(theme) {
                    apiRef.current?.updateScene({
                        appState: { theme: theme === 'auto' ? 'light' : theme },
                    });
                },
                subscribe(listener) {
                    listenersRef.current.add(listener);
                    return () => {
                        listenersRef.current.delete(listener);
                    };
                },
                flush() {
                    notify(this.getScene());
                },
            }),
            [],
        );

        useEffect(() => {
            return () => {
                listenersRef.current.clear();
                apiRef.current = null;
            };
        }, []);

        return (
            <div className="kinevo-canvas-island" style={{ height: '100%', width: '100%' }}>
                <Excalidraw
                    excalidrawAPI={(api) => {
                        apiRef.current = api as unknown as ExcalidrawApiProjection;
                    }}
                    initialData={{
                        elements: (initialScene?.elements ?? []) as unknown as never[],
                        appState: {
                            ...(initialScene?.appState ?? EMPTY_APP_STATE),
                            viewModeEnabled: initialReadOnly,
                        },
                        files: initialScene?.files as unknown as never | undefined,
                    }}
                    onChange={(elements, appState, files) => {
                        notify(
                            normalizeScene(
                                elements,
                                appState as unknown as Record<string, unknown>,
                                files as unknown as Record<string, unknown>,
                            ),
                        );
                    }}
                    theme={initialTheme === 'auto' ? 'light' : initialTheme}
                />
            </div>
        );
    },
);
