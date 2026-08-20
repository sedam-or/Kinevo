import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
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

import CanvasListView from '../CanvasListView.vue';
import { canvasApi } from '../api';
import type { CanvasDto } from '../api-types';

const canvasRow: CanvasDto = {
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
    return mount(CanvasListView, {
        global: { plugins: [pinia] },
    });
}

describe('CanvasListView', () => {
    it('loads and lists canvases', async () => {
        vi.mocked(canvasApi.list).mockResolvedValue({ canvases: [canvasRow] });
        const wrapper = mountView();
        await flushPromises();

        expect(wrapper.findAll('[data-testid="canvas-item"]')).toHaveLength(1);
        expect(wrapper.text()).toContain('Research board');
    });

    it('shows an empty state when there are no canvases', async () => {
        vi.mocked(canvasApi.list).mockResolvedValue({ canvases: [] });
        const wrapper = mountView();
        await flushPromises();

        expect(wrapper.find('[data-testid="canvas-list"]').text()).toContain('No canvases yet.');
    });

    it('emits select when a canvas is opened', async () => {
        vi.mocked(canvasApi.list).mockResolvedValue({ canvases: [canvasRow] });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('[data-testid="canvas-open"]').trigger('click');

        expect(wrapper.emitted('select')?.[0]).toEqual([3]);
    });

    it('creates a canvas and emits select', async () => {
        vi.mocked(canvasApi.list).mockResolvedValue({ canvases: [] });
        vi.mocked(canvasApi.create).mockResolvedValue({ canvas: canvasRow });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('input[data-testid="canvas-create-title"]').setValue('Research board');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(canvasApi.create).toHaveBeenCalledWith({ title: 'Research board' });
        expect(wrapper.emitted('select')?.[0]).toEqual([3]);
    });

    it('surfaces a create error', async () => {
        vi.mocked(canvasApi.list).mockResolvedValue({ canvases: [] });
        vi.mocked(canvasApi.create).mockRejectedValue({ status: 422, message: 'Invalid title', retryable: false });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('input[data-testid="canvas-create-title"]').setValue('x');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[data-testid="canvas-create"]').text()).toContain('Invalid title');
        expect(wrapper.emitted('select')).toBeUndefined();
    });
});