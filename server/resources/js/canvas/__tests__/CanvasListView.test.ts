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

vi.mock('../../ai/api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../../ai/api')>();
    return {
        ...actual,
        aiApi: {
            ...actual.aiApi,
            config: vi.fn(),
            suggestCanvas: vi.fn(),
            acceptProposalWithResult: vi.fn(),
            rejectProposal: vi.fn(),
        },
    };
});

import { aiApi, type AiProposal } from '../../ai/api';

const canvasSuggestion: AiProposal = {
    id: 31,
    user_id: 1,
    proposal_type: 'canvas',
    schema_version: 1,
    payload: {
        type: 'canvas_proposal',
        title: 'Conference planning',
        sections: [{ name: 'Talks' }, { name: 'Sponsors', description: 'Outreach and contracts' }],
    },
    decision: 'pending',
    operation_id: null,
    created_at: '2026-08-24T00:00:00Z',
};

describe('CanvasListView suggest structure (TASK-P17-029)', () => {
    function mountWithAi(configState: string): ReturnType<typeof mount> {
        vi.mocked(canvasApi.list).mockResolvedValue({ canvases: [] });
        vi.mocked(aiApi.config).mockResolvedValue({
            config: {
                provider: 'ollama', enabled: true, model: 'llama3.1', base_url: 'http://localhost:11434',
                has_api_key: false, api_key_hint: null,
                status: { provider: 'ollama', model: 'llama3.1', available: true, latency_ms: 5, error: null, state: configState },
                privacy_ok: true,
            },
        } as never);
        const pinia = createPinia();
        setActivePinia(pinia);
        return mount(CanvasListView, { global: { plugins: [pinia] } });
    }

    it('suggests a structure and creates the canvas only on acceptance (FR-62)', async () => {
        vi.mocked(aiApi.suggestCanvas).mockResolvedValue({ proposal: canvasSuggestion });
        vi.mocked(aiApi.acceptProposalWithResult).mockResolvedValue({ canvas: { id: 9, title: 'Conference planning' } });
        const wrapper = mountWithAi('connected');
        await flushPromises();

        await wrapper.find('[data-testid="canvas-suggest-prompt"]').setValue('Plan a 3-day conference');
        await wrapper.find('[data-testid="canvas-suggest-submit"]').trigger('submit');
        await flushPromises();

        expect(aiApi.suggestCanvas).toHaveBeenCalledWith('Plan a 3-day conference');
        // Preview only — nothing created yet.
        expect(wrapper.emitted('select')).toBeUndefined();
        const proposal = wrapper.find('[data-testid="canvas-suggest-proposal"]');
        expect(proposal.text()).toContain('Conference planning');
        expect(proposal.text()).toContain('Sponsors · Outreach and contracts');

        await wrapper.find('[data-testid="canvas-suggest-accept"]').trigger('click');
        await flushPromises();

        expect(aiApi.acceptProposalWithResult).toHaveBeenCalledWith(31);
        expect(wrapper.emitted('select')?.[0]).toEqual([9]);
    });

    it('rejecting the suggestion posts reject without creating anything', async () => {
        vi.mocked(aiApi.suggestCanvas).mockResolvedValue({ proposal: canvasSuggestion });
        vi.mocked(aiApi.rejectProposal).mockResolvedValue(undefined);
        const wrapper = mountWithAi('connected');
        await flushPromises();

        await wrapper.find('[data-testid="canvas-suggest-prompt"]').setValue('Plan a conference');
        await wrapper.find('[data-testid="canvas-suggest-submit"]').trigger('submit');
        await flushPromises();
        await wrapper.find('[data-testid="canvas-suggest-reject"]').trigger('click');
        await flushPromises();

        expect(aiApi.rejectProposal).toHaveBeenCalledWith(31);
        expect(wrapper.emitted('select')).toBeUndefined();
        expect(wrapper.find('[data-testid="canvas-suggest-proposal"]').exists()).toBe(false);
    });

    it('routes to Settings when AI is not configured instead of generating (P17-028 gate)', async () => {
        const wrapper = mountWithAi('not_configured');
        await flushPromises();

        await wrapper.find('[data-testid="canvas-suggest-prompt"]').setValue('Plan a conference');
        await wrapper.find('[data-testid="canvas-suggest-submit"]').trigger('submit');
        await flushPromises();

        expect(aiApi.suggestCanvas).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="ai-not-configured"]').exists()).toBe(true);
    });
});