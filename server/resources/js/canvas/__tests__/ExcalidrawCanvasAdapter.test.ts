import { describe, expect, it, vi } from 'vitest';

// Mock the React island module so its JSX/Excalidraw dependency is never
// transformed or loaded in the test environment (Excalidraw needs WebGL/canvas,
// and jsx:preserve in tsconfig is handled by the real Vite build, not vitest).
vi.mock('../react/ExcalidrawIsland', () => ({
    ExcalidrawIsland: {},
}));

import {
    ExcalidrawCanvasAdapter,
    type CanvasIslandFactory,
    type CanvasRootLike,
} from '../ExcalidrawCanvasAdapter';
import type { CanvasScene } from '../types';
import type { ExcalidrawIslandHandle } from '../react/ExcalidrawIsland';
import type { RefObject } from 'react';

/**
 * Verifies the ExcalidrawCanvasAdapter orchestration: it drives the React
 * island handle and root while keeping the Vue layer decoupled from
 * React/Excalidraw. Excalidraw requires WebGL/canvas absent in happy-dom, so
 * we inject fake island/root factories (dependency injection seam) and
 * verify the adapter's own logic — the boundary contract (ADR-005).
 */

function makeFakeHandle(): ExcalidrawIslandHandle {
    return {
        updateScene: vi.fn(),
        getScene: vi.fn(() => ({ elements: [], appState: {} })),
        setReadOnly: vi.fn(),
        setTheme: vi.fn(),
        subscribe: vi.fn(() => () => {}),
        flush: vi.fn(),
    };
}

function makeFakes() {
    const root: CanvasRootLike = {
        render: vi.fn(),
        unmount: vi.fn(),
    };
    const handle = makeFakeHandle();
    // The ref starts null (as in the real adapter before mount); tests that
    // need a mounted engine set current before calling engine-backed methods.
    const handleRef: RefObject<ExcalidrawIslandHandle | null> = { current: null };
    const islandFactory: CanvasIslandFactory = {
        create: vi.fn((props) => props as unknown),
        makeHandle: vi.fn(() => handleRef),
    };
    return { root, handleRef, handle, islandFactory };
}

const scene: CanvasScene = {
    elements: [{ id: 'a', type: 'rectangle' }],
    appState: { viewBackgroundColor: '#ffffff' },
};

describe('ExcalidrawCanvasAdapter', () => {
    it('mounts the island with initial props and a ref', () => {
        const { root, handleRef, islandFactory } = makeFakes();
        const adapter = new ExcalidrawCanvasAdapter(islandFactory, () => root);
        const host = document.createElement('div');

        adapter.mount(host);

        expect(islandFactory.create).toHaveBeenCalled();
        const props = (islandFactory.create as ReturnType<typeof vi.fn>).mock
            .calls[0][0];
        expect(props.ref).toBe(handleRef);
        expect(props.initialReadOnly).toBe(false);
        expect(props.initialTheme).toBe('auto');
        expect(root.render).toHaveBeenCalled();
    });

    it('load pushes a scene into the engine handle', () => {
        const { root, handle, handleRef, islandFactory } = makeFakes();
        const adapter = new ExcalidrawCanvasAdapter(islandFactory, () => root);
        adapter.mount(document.createElement('div'));
        handleRef.current = handle;

        adapter.load(scene);

        expect(handle.updateScene).toHaveBeenCalledWith(scene);
    });

    it('save returns the scene from the engine with the base version', () => {
        const { root, handle, handleRef, islandFactory } = makeFakes();
        const adapter = new ExcalidrawCanvasAdapter(islandFactory, () => root);
        adapter.mount(document.createElement('div'));
        handleRef.current = handle;

        (handle.getScene as ReturnType<typeof vi.fn>).mockReturnValue(scene);

        const result = adapter.save(7);

        expect(result.baseVersion).toBe(7);
        expect(result.scene).toBe(scene);
    });

    it('save before mount returns the last loaded scene', () => {
        const { root, islandFactory } = makeFakes();
        const adapter = new ExcalidrawCanvasAdapter(islandFactory, () => root);

        adapter.load(scene);
        const result = adapter.save(1);

        expect(result.scene).toEqual(scene);
        expect(result.baseVersion).toBe(1);
    });

    it('setReadOnly and setTheme forward to the engine', () => {
        const { root, handle, handleRef, islandFactory } = makeFakes();
        const adapter = new ExcalidrawCanvasAdapter(islandFactory, () => root);
        adapter.mount(document.createElement('div'));
        handleRef.current = handle;

        adapter.setReadOnly(true);
        adapter.setTheme('dark');

        expect(handle.setReadOnly).toHaveBeenCalledWith(true);
        expect(handle.setTheme).toHaveBeenCalledWith('dark');
    });

    it('propagates scene changes to subscribers via the island callback', () => {
        const { root, islandFactory } = makeFakes();
        const adapter = new ExcalidrawCanvasAdapter(islandFactory, () => root);
        adapter.mount(document.createElement('div'));

        const listener = vi.fn();
        adapter.subscribe(listener);

        // Capture the onSceneChange callback passed to the island
        const props = (islandFactory.create as ReturnType<typeof vi.fn>).mock
            .calls[0][0];
        const onSceneChange = props.onSceneChange as (s: CanvasScene) => void;
        onSceneChange(scene);

        expect(listener).toHaveBeenCalledWith({ scene });
    });

    it('unsubscribe stops change delivery', () => {
        const { root, islandFactory } = makeFakes();
        const adapter = new ExcalidrawCanvasAdapter(islandFactory, () => root);
        adapter.mount(document.createElement('div'));

        const listener = vi.fn();
        const unsubscribe = adapter.subscribe(listener);
        unsubscribe();

        const props = (islandFactory.create as ReturnType<typeof vi.fn>).mock
            .calls[0][0];
        const onSceneChange = props.onSceneChange as (s: CanvasScene) => void;
        onSceneChange(scene);

        expect(listener).not.toHaveBeenCalled();
    });

    it('flush delegates to the island handle', () => {
        const { root, handle, handleRef, islandFactory } = makeFakes();
        const adapter = new ExcalidrawCanvasAdapter(islandFactory, () => root);
        adapter.mount(document.createElement('div'));
        handleRef.current = handle;

        adapter.flush();

        expect(handle.flush).toHaveBeenCalled();
    });

    it('destroy unmounts the root, clears listeners and host', () => {
        const { root, handleRef, handle, islandFactory } = makeFakes();
        const adapter = new ExcalidrawCanvasAdapter(islandFactory, () => root);
        const host = document.createElement('div');
        host.innerHTML = '<span>canvas</span>';
        adapter.mount(host);
        handleRef.current = handle;

        const listener = vi.fn();
        adapter.subscribe(listener);
        adapter.destroy();

        expect(root.unmount).toHaveBeenCalled();
        expect(handleRef.current).toBeNull();
        expect(host.childNodes.length).toBe(0);
    });
});
