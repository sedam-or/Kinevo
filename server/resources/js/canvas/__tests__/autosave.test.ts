import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    CanvasAutosaveController,
    type CanvasPersistence,
    type CanvasSaveStateChange,
} from '../autosave';
import type { CanvasAdapter, CanvasScene } from '../types';

function makeAdapter(): CanvasAdapter {
    const listeners = new Set<(change: { scene: CanvasScene }) => void>();
    return {
        mount: vi.fn(),
        load: vi.fn(),
        getScene: vi.fn(() => ({ elements: [], appState: {} })),
        save: vi.fn((baseVersion: number) => ({ scene: { elements: [], appState: {} }, baseVersion })),
        setReadOnly: vi.fn(),
        setTheme: vi.fn(),
        subscribe: vi.fn((listener) => {
            listeners.add(listener);
            return () => listeners.delete(listener);
        }),
        flush: vi.fn(),
        destroy: vi.fn(),
    };
}

const scene: CanvasScene = {
    elements: [{ id: 'a', type: 'rectangle' }],
    appState: {},
};

/** Capture the adapter's change-notification callback to simulate edits. */
function captureNotifier(adapter: CanvasAdapter): (s: CanvasScene) => void {
    const subscribe = adapter.subscribe as ReturnType<typeof vi.fn>;
    const listener = subscribe.mock.calls[0][0];
    return (s: CanvasScene) => listener({ scene: s });
}

function makePersistence(overrides: Partial<CanvasPersistence> = {}): CanvasPersistence {
    return {
        save: vi.fn().mockResolvedValue({ version: 1 }),
        ...overrides,
    };
}

describe('CanvasAutosaveController', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('starts idle and subscribes to the adapter', () => {
        const adapter = makeAdapter();
        const persistence = makePersistence();
        const controller = new CanvasAutosaveController(adapter, persistence, 42, 5);

        expect(controller.getState()).toBe('idle');
        expect(adapter.subscribe).toHaveBeenCalled();
        expect(controller.getBaseVersion()).toBe(5);
        controller.dispose();
    });

    it('marks dirty on scene change then saves with base version after debounce', async () => {
        const adapter = makeAdapter();
        const persistence = makePersistence({ save: vi.fn().mockResolvedValue({ version: 6 }) });
        const controller = new CanvasAutosaveController(adapter, persistence, 42, 5, 50);
        const notify = captureNotifier(adapter);

        const states: CanvasSaveStateChange[] = [];
        controller.subscribe((s) => states.push(s));

        notify(scene);
        expect(controller.getState()).toBe('dirty');

        await vi.advanceTimersByTimeAsync(50);
        await vi.runAllTimersAsync();

        expect(persistence.save).toHaveBeenCalledWith(42, 5, scene);
        expect(controller.getBaseVersion()).toBe(6);
        expect(controller.getState()).toBe('saved');
        expect(states.some((s) => s.state === 'saving')).toBe(true);
        expect(states.some((s) => s.state === 'saved' && s.version === 6)).toBe(true);
        controller.dispose();
    });

    it('a continuous change stream cannot starve autosave; the save carries the latest scene', async () => {
        const adapter = makeAdapter();
        const persistence = makePersistence({ save: vi.fn().mockResolvedValue({ version: 7 }) });
        const controller = new CanvasAutosaveController(adapter, persistence, 42, 5, 100);
        const notify = captureNotifier(adapter);

        // Emits faster than the debounce window — a re-arming debounce would
        // never fire (TASK-R4 regression: Excalidraw echo loop starved saves).
        for (let i = 0; i < 10; i++) {
            notify({ elements: [{ id: `e${i}`, type: 'rectangle' }], appState: {} });
            await vi.advanceTimersByTimeAsync(20);
        }
        await vi.advanceTimersByTimeAsync(100);

        // The stream outlives one debounce window, so more than one save is
        // correct — what must NEVER happen is zero saves (starvation), and
        // the last save must carry the newest scene.
        const calls = (persistence.save as ReturnType<typeof vi.fn>).mock.calls;
        expect(calls.length).toBeGreaterThanOrEqual(1);
        expect(calls[calls.length - 1][2]).toEqual({
            elements: [{ id: 'e9', type: 'rectangle' }],
            appState: {},
        });
        expect(controller.getState()).toBe('saved');
        controller.dispose();
    });

    it('conflict pauses autosave and surfaces conflict state', async () => {
        const adapter = makeAdapter();
        const err = new Error('stale') as Error & { code?: string };
        err.code = 'CANVAS_VERSION_CONFLICT';
        const persistence = makePersistence({ save: vi.fn().mockRejectedValue(err) });
        const controller = new CanvasAutosaveController(adapter, persistence, 42, 5, 50);
        const notify = captureNotifier(adapter);

        const states: CanvasSaveStateChange[] = [];
        controller.subscribe((s) => states.push(s));

        notify(scene);
        await vi.advanceTimersByTimeAsync(50);
        await vi.runAllTimersAsync();

        expect(controller.getState()).toBe('conflict');
        expect(states.some((s) => s.state === 'conflict')).toBe(true);

        // While in conflict, further edits must NOT trigger another save.
        (persistence.save as ReturnType<typeof vi.fn>).mockClear();
        notify(scene);
        await vi.advanceTimersByTimeAsync(50);
        await vi.runAllTimersAsync();
        expect(persistence.save).not.toHaveBeenCalled();
        controller.dispose();
    });

    it('offline failure surfaces offline state', async () => {
        const adapter = makeAdapter();
        const err = new Error('no network') as Error & { code?: string };
        err.code = 'OFFLINE';
        const persistence = makePersistence({ save: vi.fn().mockRejectedValue(err) });
        const controller = new CanvasAutosaveController(adapter, persistence, 42, 0, 50);
        const notify = captureNotifier(adapter);

        const states: CanvasSaveStateChange[] = [];
        controller.subscribe((s) => states.push(s));

        notify(scene);
        await vi.advanceTimersByTimeAsync(50);
        await vi.runAllTimersAsync();

        expect(controller.getState()).toBe('offline');
        expect(states.some((s) => s.state === 'offline')).toBe(true);
        controller.dispose();
    });

    it('generic failure surfaces failed state', async () => {
        const adapter = makeAdapter();
        const persistence = makePersistence({
            save: vi.fn().mockRejectedValue(new Error('boom')),
        });
        const controller = new CanvasAutosaveController(adapter, persistence, 42, 0, 50);
        const notify = captureNotifier(adapter);

        const states: CanvasSaveStateChange[] = [];
        controller.subscribe((s) => states.push(s));

        notify(scene);
        await vi.advanceTimersByTimeAsync(50);
        await vi.runAllTimersAsync();

        expect(controller.getState()).toBe('failed');
        expect(states.some((s) => s.state === 'failed')).toBe(true);
        controller.dispose();
    });

    it('flush triggers an immediate save, bypassing debounce', async () => {
        const adapter = makeAdapter();
        const persistence = makePersistence({ save: vi.fn().mockResolvedValue({ version: 2 }) });
        const controller = new CanvasAutosaveController(adapter, persistence, 42, 1, 5000);
        const notify = captureNotifier(adapter);

        notify(scene);
        await controller.flush();
        await vi.advanceTimersByTimeAsync(0);
        await vi.runAllTimersAsync();

        expect(persistence.save).toHaveBeenCalledWith(42, 1, scene);
        expect(controller.getState()).toBe('saved');
        controller.dispose();
    });

    it('reconcile adopts server version and scene, returning to idle', async () => {
        const adapter = makeAdapter();
        const persistence = makePersistence({
            save: vi.fn().mockRejectedValue(
                Object.assign(new Error('stale'), { code: 'CANVAS_VERSION_CONFLICT' }),
            ),
        });
        const controller = new CanvasAutosaveController(adapter, persistence, 42, 3, 50);
        const notify = captureNotifier(adapter);

        notify(scene);
        await vi.advanceTimersByTimeAsync(50);
        await vi.runAllTimersAsync();
        expect(controller.getState()).toBe('conflict');

        const serverScene: CanvasScene = { elements: [{ id: 'server', type: 'text' }], appState: {} };
        controller.reconcile(serverScene, 9);

        expect(adapter.load).toHaveBeenCalledWith(serverScene);
        expect(controller.getBaseVersion()).toBe(9);
        expect(controller.getState()).toBe('idle');

        // Subsequent edits save from the reconciled version.
        (persistence.save as ReturnType<typeof vi.fn>).mockResolvedValue({ version: 10 });
        notify(serverScene);
        await vi.advanceTimersByTimeAsync(50);
        await vi.runAllTimersAsync();
        expect(persistence.save).toHaveBeenCalledWith(42, 9, serverScene);
        controller.dispose();
    });

    it('dispose stops autosave and clears listeners', async () => {
        const adapter = makeAdapter();
        const persistence = makePersistence();
        const controller = new CanvasAutosaveController(adapter, persistence, 42, 0, 50);
        const notify = captureNotifier(adapter);

        const listener = vi.fn();
        controller.subscribe(listener);
        controller.dispose();

        notify(scene);
        await vi.advanceTimersByTimeAsync(100);
        await vi.runAllTimersAsync();

        expect(persistence.save).not.toHaveBeenCalled();
        expect(controller.getState()).toBe('idle');
    });
});
