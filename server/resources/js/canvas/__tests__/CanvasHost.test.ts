import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CanvasHost from '../CanvasHost.vue';
import type { CanvasAdapter } from '../types';

// The React island is never loaded/transformed in the test environment
// (Excalidraw needs WebGL/canvas and pulls in native modules). Every test
// overrides the adapter via adapterFactory, so the real default adapter is
// never booted here.
vi.mock('../react/ExcalidrawIsland', () => ({
    ExcalidrawIsland: {},
}));

function fakeAdapter(overrides: Partial<CanvasAdapter> = {}): CanvasAdapter {
    return {
        mount: () => {},
        load: () => {},
        getScene: () => ({ elements: [], appState: {} }),
        save: (baseVersion: number) => ({ scene: { elements: [], appState: {} }, baseVersion }),
        setReadOnly: () => {},
        setTheme: () => {},
        subscribe: () => () => {},
        flush: () => {},
        destroy: () => {},
        ...overrides,
    };
}

describe('CanvasHost entry states (design.md §34.2)', () => {
    it('transitions loading → ready and emits ready on successful boot', async () => {
        const wrapper = mount(CanvasHost, {
            props: {
                adapterFactory: () => fakeAdapter(),
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="canvas-host"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="canvas-editor-loading"]').exists()).toBe(false);
        expect(wrapper.emitted('ready')).toHaveLength(1);
    });

    it('shows a failure surface (never a blank page) when the editor fails to boot', async () => {
        const wrapper = mount(CanvasHost, {
            props: {
                adapterFactory: () =>
                    fakeAdapter({
                        mount: () => {
                            throw new Error('boom');
                        },
                    }),
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="canvas-editor-error"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="canvas-host"]').classes()).toContain('invisible');
        expect(wrapper.text()).toContain('Canvas editor failed to initialize.');
        expect(wrapper.find('[data-testid="canvas-editor-retry"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="canvas-editor-readonly"]').exists()).toBe(true);
    });

    it('recovers to ready when Retry succeeds on the second boot', async () => {
        let fail = true;
        const wrapper = mount(CanvasHost, {
            props: {
                adapterFactory: () =>
                    fakeAdapter({
                        mount: () => {
                            if (fail) {
                                throw new Error('first boot fails');
                            }
                        },
                    }),
            },
        });
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-testid="canvas-editor-error"]').exists()).toBe(true);

        fail = false;
        await wrapper.find('[data-testid="canvas-editor-retry"]').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="canvas-host"]').classes()).not.toContain('invisible');
        expect(wrapper.find('[data-testid="canvas-editor-error"]').exists()).toBe(false);
    });
});
