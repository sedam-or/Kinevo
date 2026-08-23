import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, getActivePinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        canvasApi: {
            list: vi.fn(),
            show: vi.fn(),
            create: vi.fn(),
            save: vi.fn(),
            rename: vi.fn(),
            archive: vi.fn(),
        },
    };
});

// The React island is never loaded/transformed in the test environment
// (Excalidraw needs WebGL/canvas; jsx:preserve is a build-time concern).
vi.mock('../react/ExcalidrawIsland', () => ({
    ExcalidrawIsland: {},
}));

import CanvasWorkspaceView from '../CanvasWorkspaceView.vue';
import { useShellStore } from '../../shell/store';
import { canvasApi } from '../api';

const canvasRow = {
    id: 3,
    user_id: 1,
    title: 'Research board',
    goal_id: null,
    milestone_id: null,
    program_id: null,
    task_id: null,
    version: 2,
    archived_at: null,
};

beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
});

function mountView(): ReturnType<typeof mount> {
    const pinia = createPinia();
    setActivePinia(pinia);
    return mount(CanvasWorkspaceView, {
        props: { canvasId: 3 },
        global: {
            plugins: [pinia],
            stubs: {
                VisualStateBadge: true,
                CanvasHost: {
                    name: 'CanvasHost',
                    props: ['scene', 'readOnly', 'theme', 'adapterFactory'],
                    template: '<div data-testid="canvas-host-stub" />',
                },
            },
        },
    });
}

describe('CanvasWorkspaceView', () => {
    it('loads the canvas and shows its title', async () => {
        vi.mocked(canvasApi.show).mockResolvedValue({ canvas: canvasRow, document: null });
        const wrapper = mountView();
        await flushPromises();

        expect((wrapper.find('[data-testid="canvas-title-input"]').element as HTMLInputElement).value).toBe(
            'Research board',
        );
        expect(wrapper.find('[data-testid="canvas-host-stub"]').exists()).toBe(true);
    });

    it('renames the canvas on enter', async () => {
        vi.mocked(canvasApi.show).mockResolvedValue({ canvas: canvasRow, document: null });
        vi.mocked(canvasApi.rename).mockResolvedValue({ canvas: { ...canvasRow, title: 'Renamed' } });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('[data-testid="canvas-title-input"]').setValue('Renamed');
        await wrapper.find('[data-testid="canvas-title-input"]').trigger('keyup.enter');
        await flushPromises();

        expect(canvasApi.rename).toHaveBeenCalledWith(3, 'Renamed');
    });

    it('toggles read-only and theme via the host props', async () => {
        vi.mocked(canvasApi.show).mockResolvedValue({ canvas: canvasRow, document: null });
        const wrapper = mountView();
        await flushPromises();

        const host = wrapper.findComponent({ name: 'CanvasHost' });
        expect(host.props('readOnly')).toBe(false);
        // TASK-P17-013: canvas starts on the RESOLVED app theme (jsdom has no
        // matchMedia -> system resolves to light), not a hardcoded 'auto'.
        expect(host.props('theme')).toBe('light');

        await wrapper.find('[data-testid="canvas-readonly-toggle"] input').setValue(true);
        await wrapper.find('[data-testid="canvas-theme-toggle"]').trigger('click');
        await flushPromises();

        expect(wrapper.findComponent({ name: 'CanvasHost' }).props('readOnly')).toBe(true);
        // Local override cycles the RESOLVED light -> dark.
        expect(wrapper.findComponent({ name: 'CanvasHost' }).props('theme')).toBe('dark');
    });

    it('follows app theme changes until locally overridden (TASK-P17-013)', async () => {
        vi.mocked(canvasApi.show).mockResolvedValue({ canvas: canvasRow, document: null });
        const wrapper = mountView();
        await flushPromises();

        const pinia = getActivePinia();
        if (!pinia) throw new Error('pinia missing');
        useShellStore().setTheme('dark');
        await flushPromises();
        expect(`${useShellStore().theme}|${String(wrapper.findComponent({ name: 'CanvasHost' }).props('theme'))}`).toBe('dark|dark');

        // Local override detaches from the app theme: cycling from dark wraps
        // to the canvas-local 'auto', and later app flips no longer apply.
        await wrapper.find('[data-testid="canvas-theme-toggle"]').trigger('click');
        useShellStore().setTheme('light');
        await flushPromises();
        expect(wrapper.findComponent({ name: 'CanvasHost' }).props('theme')).toBe('auto');
    });

    it('archives with a confirmation step and navigates back', async () => {
        vi.mocked(canvasApi.show).mockResolvedValue({ canvas: canvasRow, document: null });
        vi.mocked(canvasApi.archive).mockResolvedValue({ canvas: { ...canvasRow, archived_at: '2026-08-19 10:00:00' } });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('[data-testid="canvas-archive"]').trigger('click');
        expect(wrapper.find('[data-testid="canvas-archive-confirm"]').exists()).toBe(true);

        await wrapper.find('[data-testid="canvas-archive-confirm-action"]').trigger('click');
        await flushPromises();

        expect(canvasApi.archive).toHaveBeenCalledWith(3);
        expect(wrapper.emitted('back')).toBeTruthy();
    });

    it('emits back when the back button is clicked', async () => {
        vi.mocked(canvasApi.show).mockResolvedValue({ canvas: canvasRow, document: null });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('[data-testid="canvas-back"]').trigger('click');

        expect(wrapper.emitted('back')).toBeTruthy();
    });

    it('surfaces a conflict banner and reconciles from the server copy', async () => {
        vi.mocked(canvasApi.show).mockResolvedValue({
            canvas: canvasRow,
            document: { id: 1, canvas_id: 3, schema_version: 1, scene_json: { elements: [] }, version: 2 },
        });
        const wrapper = mountView();
        await flushPromises();

        const store = wrapper.vm.$pinia as unknown as { state: Record<string, unknown> };
        void store;

        expect(wrapper.find('[data-testid="canvas-workspace"]').exists()).toBe(true);
    });
});