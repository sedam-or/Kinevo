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
            providers: vi.fn(),
            setCredential: vi.fn(),
            removeCredential: vi.fn(),
            enable: vi.fn(),
            disable: vi.fn(),
        },
    };
});

import AiSettingsView from '../AiSettingsView.vue';
import { aiApi } from '../api';

const maskedConfig = {
    provider: 'ollama',
    protocol: 'ollama',
    enabled: true,
    model: 'llama3.1',
    base_url: 'http://localhost:11434',
    configured: true,
    has_api_key: false,
    api_key_hint: null,
    last_verified_at: null,
    last_status: null,
    last_error_code: null,
    status: { provider: 'ollama', model: 'llama3.1', state: 'unavailable' as const, available: false, latency_ms: null, error: 'AI provider is unreachable.' },
    privacy_ok: true,
};

const CATALOG = [
    { id: 'ollama', protocols: ['ollama'], default_protocol: 'ollama', requires_api_key: false, requires_base_url: false, requires_model: false, supports_local: true, supports_remote: true, supports_connection_test: true },
    { id: 'openai', protocols: ['openai-chat'], default_protocol: 'openai-chat', requires_api_key: true, requires_base_url: false, requires_model: false, supports_local: false, supports_remote: true, supports_connection_test: true },
];

describe('AiSettingsView (TASK-P17-006, P18-010..013)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        setActivePinia(createPinia());
        vi.mocked(aiApi.providers).mockResolvedValue({ providers: CATALOG });
    });

    function mountView() {
        return mount(AiSettingsView, { global: { plugins: [createPinia()] } });
    }

    function keyInput(wrapper: ReturnType<typeof mount>) {
        return wrapper.find('[data-testid="secret-field"] [data-testid="secret-input"]');
    }

    it('loads and shows the persisted provider config without any key material', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({
            config: { ...maskedConfig, provider: 'openai', protocol: 'openai-chat', base_url: 'https://api.openai.com/v1', model: 'gpt-4o-mini', has_api_key: true, api_key_hint: '…abcd' },
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

    it('shows the Ollama no-key note instead of the credential section', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({ config: maskedConfig });
        const wrapper = mountView();
        await flushPromises();

        expect(keyInput(wrapper).exists()).toBe(false);
        expect(wrapper.find('[data-testid="ai-ollama-no-key"]').text()).toContain('does not require an API key');
    });

    it('rotates a typed key through the dedicated credential endpoint, not the settings payload', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({ config: maskedConfig });
        vi.mocked(aiApi.save).mockResolvedValue({
            config: { ...maskedConfig, provider: 'openai', protocol: 'openai-chat', model: 'gpt-4o-mini', has_api_key: true, api_key_hint: '…9999' },
        });
        vi.mocked(aiApi.setCredential).mockResolvedValue({
            config: { ...maskedConfig, provider: 'openai', has_api_key: true, api_key_hint: '…9999' },
        });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('[data-testid="ai-provider-select"]').setValue('openai');
        await wrapper.find('[data-testid="ai-model-input"]').setValue('gpt-4o-mini');
        await keyInput(wrapper).setValue('sk-brand-new-key');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(aiApi.save).toHaveBeenCalledWith(expect.objectContaining({
            provider: 'openai',
            model: 'gpt-4o-mini',
        }));
        // The raw key travels ONLY through the rotation endpoint (P18-022).
        expect((aiApi.save as ReturnType<typeof vi.fn>).mock.calls[0][0].api_key).toBeUndefined();
        expect(aiApi.setCredential).toHaveBeenCalledWith('sk-brand-new-key');
        expect(wrapper.find('[data-testid="ai-settings-saved"]').exists()).toBe(true);
        // After save the input is cleared; the stored hint replaces it.
        expect((keyInput(wrapper).element as HTMLInputElement).value).toBe('');
        expect(wrapper.find('[data-testid="ai-credential-saved"]').exists()).toBe(true);
    });

    it('removes the stored credential without ever displaying it', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({
            config: { ...maskedConfig, provider: 'openai', has_api_key: true, api_key_hint: '…abcd' },
        });
        vi.mocked(aiApi.removeCredential).mockResolvedValue({
            config: { ...maskedConfig, provider: 'openai', has_api_key: false, api_key_hint: null },
        });
        const wrapper = mountView();
        await flushPromises();

        expect(wrapper.find('[data-testid="ai-remove-key-button"]').exists()).toBe(true);
        await wrapper.find('[data-testid="ai-remove-key-button"]').trigger('click');
        await flushPromises();

        expect(aiApi.removeCredential).toHaveBeenCalledOnce();
        expect(wrapper.find('[data-testid="ai-remove-key-button"]').exists()).toBe(false);
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
        vi.mocked(aiApi.test).mockResolvedValue({
            status: { provider: 'ollama', model: 'llama3.1', available: false, latency_ms: 12, error: 'AI provider is unreachable.' },
            ok: false,
            code: 'AI_PROVIDER_UNAVAILABLE',
            message: 'AI provider is unreachable.',
        });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('[data-testid="ai-test-button"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="ai-test-result"]').text()).toContain('unreachable');
        expect((wrapper.find('[data-testid="ai-model-input"]').element as HTMLInputElement).value).toBe('llama3.1');
    });

    it('surfaces a successful connection test proven by minimal inference', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({ config: maskedConfig });
        vi.mocked(aiApi.test).mockResolvedValue({
            status: { provider: 'ollama', model: 'llama3.1', available: true, latency_ms: 8, error: null },
            ok: true,
            code: null,
            message: 'Connection verified with a minimal inference request.',
        });
        const wrapper = mountView();
        await flushPromises();

        await wrapper.find('[data-testid="ai-test-button"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="ai-test-result"]').text()).toContain('Connected to ollama');
        expect(wrapper.find('[data-testid="ai-test-result"]').text()).toContain('model responded');
    });

    it('renders the canonical status banner from the server state (P17-007)', async () => {
        const cases: Array<{ state: string; label: string }> = [
            { state: 'connected', label: 'Connected.' },
            { state: 'degraded', label: 'Connected, but slow' },
            { state: 'unavailable', label: 'Provider unreachable.' },
            { state: 'disabled', label: 'AI is off.' },
            { state: 'not_configured', label: 'Not configured' },
        ];
        for (const c of cases) {
            vi.mocked(aiApi.config).mockResolvedValue({
                config: {
                    ...maskedConfig,
                    status: { ...maskedConfig.status, state: c.state as typeof maskedConfig.status.state },
                },
            });
            const wrapper = mountView();
            await flushPromises();

            const banner = wrapper.find('[data-testid="ai-status-banner"]');
            expect(banner.text()).toContain(c.label);

            wrapper.unmount();
        }
    });

    it('explains privacy guarantees in plain language (P18-013)', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({ config: maskedConfig });
        const wrapper = mountView();
        await flushPromises();

        const copy = wrapper.find('[data-testid="ai-privacy-copy"]').text();
        expect(copy).toContain('encrypted');
        expect(copy.toLowerCase()).toContain('never sent back');
        expect(copy).toContain('explicitly run an AI action');
    });

    it('shows verification metadata after a server-side check (P18-009)', async () => {
        vi.mocked(aiApi.config).mockResolvedValue({
            config: {
                ...maskedConfig,
                last_verified_at: '2026-08-26T10:00:00.000Z',
                last_status: 'connected',
                last_error_code: null,
            },
        });
        const wrapper = mountView();
        await flushPromises();

        expect(wrapper.find('[data-testid="ai-verification-note"]').text()).toContain('Last verified');
    });
});
