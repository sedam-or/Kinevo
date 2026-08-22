import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../api', async (importOriginal) => {
    const actual = await importOriginal<typeof import('../api')>();
    return {
        ...actual,
        aiApi: {
            config: vi.fn(),
            save: vi.fn(),
            test: vi.fn(),
        },
    };
});

import AiSettingsView from '../AiSettingsView.vue';
import { aiApi } from '../api';

const maskedConfig = {
    provider: 'ollama',
    enabled: true,
    model: 'llama3.1',
    base_url: 'http://localhost:11434',
    has_api_key: false,
    api_key_hint: null,
    status: { provider: 'ollama', model: 'llama3.1', available: false, latency_ms: null, error: 'AI provider is unreachable.' },
    privacy_ok: true,
};

describe('AiSettingsView (TASK-P17-006)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        setActivePinia(createPinia());
    });

    function mountView() {
        return mount(AiSettingsView, { global: { plugins: [createPinia()] } });
    }

    it('loads and shows the persisted provider config without any key material', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({
            config: { ...maskedConfig, provider: 'openai', base_url: 'https://api.openai.com/v1', model: 'gpt-4o-mini', has_api_key: true, api_key_hint: '…abcd' },
        });
        const wrapper = mountView();
        await flushPromises();

        expect(wrapper.find('[data-testid="ai-settings-view"]').exists()).toBe(true);
        expect((wrapper.find('[data-testid="ai-provider-select"]').element as HTMLSelectElement).value).toBe('openai');
        expect((wrapper.find('[data-testid="ai-model-input"]').element as HTMLInputElement).value).toBe('gpt-4o-mini');
        // Masked hint only; never the raw key.
        expect(wrapper.find('[data-testid="ai-api-key-hint"]').text()).toContain('…abcd');
        const html = wrapper.html();
        expect(html).not.toContain('sk-super-secret');
        expect(aiApi.config).toHaveBeenCalledOnce();
    });

    it('shows the Ollama no-key note instead of the key field', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({ config: maskedConfig });
        const wrapper = mountView();
        await flushPromises();

        expect(wrapper.find('[data-testid="ai-api-key-input"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="ai-ollama-no-key"]').text()).toContain('does not require an API key');
    });

    it('saves the form and reports success without echoing the key back into the form', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({ config: maskedConfig });
        vi.mocked(aiApi.save).mockResolvedValue({
            config: { ...maskedConfig, provider: 'openai', base_url: 'https://api.openai.com/v1', model: 'gpt-4o-mini', has_api_key: true, api_key_hint: '…9999' },
        });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('[data-testid="ai-provider-select"]').setValue('openai');
        await wrapper.find('[data-testid="ai-model-input"]').setValue('gpt-4o-mini');
        await wrapper.find('[data-testid="ai-api-key-input"]').setValue('sk-brand-new-key');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(aiApi.save).toHaveBeenCalledWith(expect.objectContaining({
            provider: 'openai',
            model: 'gpt-4o-mini',
            api_key: 'sk-brand-new-key',
        }));
        expect(wrapper.find('[data-testid="ai-settings-saved"]').exists()).toBe(true);
        // After save the input is cleared; the stored hint replaces it.
        expect((wrapper.find('[data-testid="ai-api-key-input"]').element as HTMLInputElement).value).toBe('');
    });

    it('blocks saving OpenAI without a key and without a stored key', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({ config: { ...maskedConfig, provider: 'disabled', has_api_key: false } });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('[data-testid="ai-provider-select"]').setValue('openai');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[data-testid="ai-settings-error"]').text()).toContain('API key');
        expect(aiApi.save).not.toHaveBeenCalled();
    });

    it('surfaces a failed connection test without losing form state', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({ config: maskedConfig });
        vi.mocked(aiApi.test).mockResolvedValue({ status: { provider: 'ollama', model: 'llama3.1', available: false, latency_ms: 12, error: 'AI provider is unreachable.' } });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('[data-testid="ai-test-button"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="ai-test-result"]').text()).toContain('unreachable');
        expect((wrapper.find('[data-testid="ai-model-input"]').element as HTMLInputElement).value).toBe('llama3.1');
    });

    it('surfaces a successful connection test', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({ config: maskedConfig });
        vi.mocked(aiApi.test).mockResolvedValue({ status: { provider: 'ollama', model: 'llama3.1', available: true, latency_ms: 8, error: null } });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('[data-testid="ai-test-button"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="ai-test-result"]').text()).toContain('Connected to ollama');
    });

    it('shows the privacy blurb', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({ config: maskedConfig });
        const wrapper = mountView();
        await flushPromises();

        expect(wrapper.find('[data-testid="ai-privacy-blurb"]').text()).toContain('never stored in browser storage');
    });
});