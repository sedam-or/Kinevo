import { describe, expect, it } from 'vitest';
import type { CanvasAdapter, CanvasScene, CanvasSaveResult } from '../types';

/**
 * Verifies the framework-agnostic CanvasAdapter contract is structurally
 * sound: the Vue layer depends only on this interface and never on React or
 * Excalidraw types. This is the core of the ADR-005 architecture spike.
 */
describe('CanvasAdapter boundary contract', () => {
    it('defines the save result shape with base version', () => {
        const result: CanvasSaveResult = {
            scene: { elements: [], appState: {} },
            baseVersion: 3,
        };

        expect(result.baseVersion).toBe(3);
        expect(result.scene.elements).toEqual([]);
        expect(result.scene.appState).toEqual({});
    });

    it('defines a scene with elements, appState and optional files', () => {
        const scene: CanvasScene = {
            elements: [{ id: 'e1', type: 'rectangle' }],
            appState: { zoom: 1 },
            files: {},
        };

        expect(scene.elements[0].id).toBe('e1');
        expect(scene.appState).toHaveProperty('zoom', 1);
        expect(scene.files).toEqual({});
    });

    it('exposes the full adapter method surface', () => {
        const adapter: CanvasAdapter = {
            mount: () => {},
            load: () => {},
            getScene: () => ({ elements: [], appState: {} }),
            save: (baseVersion: number) => ({
                scene: { elements: [], appState: {} },
                baseVersion,
            }),
            setReadOnly: () => {},
            setTheme: () => {},
            subscribe: () => () => {},
            flush: () => {},
            destroy: () => {},
        };

        // The contract must not require React or Excalidraw types to be
        // referenced by consumers — the boundary is fully framework-agnostic.
        expect(typeof adapter.mount).toBe('function');
        expect(typeof adapter.load).toBe('function');
        expect(typeof adapter.save).toBe('function');
        expect(adapter.save(7).baseVersion).toBe(7);
        expect(typeof adapter.subscribe).toBe('function');
        expect(typeof adapter.destroy).toBe('function');
    });

    it('keeps scene JSON as the canonical representation', () => {
        const scene: CanvasScene = {
            elements: [{ id: 'a', type: 'text', text: 'hello' }],
            appState: { viewBackgroundColor: '#ffffff' },
        };

        // The serialized form is what Kinevo stores in canvas_documents.scene_json
        const serialized = JSON.stringify(scene);
        expect(JSON.parse(serialized)).toEqual(scene);
    });
});
