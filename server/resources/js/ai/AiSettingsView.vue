<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useAiSettingsStore } from './store';
import { aiApi, type AiProviderCatalogEntry, type AiStatusState } from './api';
import KButton from '../components/KButton.vue';
import SecretField from './SecretField.vue';
import AiUsageSummaryCard from './AiUsageSummaryCard.vue';

const store = useAiSettingsStore();

// Capability-driven UI (TASK-P18-012): fields derive from the server catalog
// instead of scattered per-provider conditionals in the component.
const PROVIDER_LABELS: Record<string, string> = {
    disabled: 'Disabled',
    ollama: 'Ollama (local)',
    openai: 'OpenAI-compatible',
    mock: 'Mock (testing)',
};

const form = reactive({
    provider: 'disabled',
    enabled: true,
    model: '',
    baseUrl: '',
});
const apiKeyInput = ref('');
const saved = ref(false);
const credentialSaved = ref(false);
const saving = ref(false);
const testing = ref(false);
const testResult = ref<null | { ok: boolean; text: string }>(null);
const formError = ref<string | null>(null);
const catalog = ref<AiProviderCatalogEntry[]>([]);

// Single source of truth: render the server's canonical state (P17-007),
// never re-derive availability from raw fields in components.
const STATUS_VIEW: Record<AiStatusState, { tone: string; label: string }> = {
    disabled: { tone: 'text-gray-600 dark:text-gray-400', label: 'AI is off.' },
    not_configured: {
        tone: 'text-warning dark:text-yellow-400',
        label: 'Not configured — finish provider setup to use AI.',
    },
    configured: {
        tone: 'text-gray-600 dark:text-gray-400',
        label: 'Saved — not yet verified.',
    },
    testing: { tone: 'text-gray-600 dark:text-gray-400', label: 'Testing connection…' },
    connected: { tone: 'text-green-700 dark:text-green-400', label: 'Connected.' },
    degraded: {
        tone: 'text-warning dark:text-yellow-400',
        label: 'Connected, but slow — responses may lag.',
    },
    unavailable: { tone: 'text-danger', label: 'Provider unreachable.' },
};
const statusState = computed<AiStatusState>(() => store.config?.status.state ?? 'disabled');
const statusView = computed(() => STATUS_VIEW[statusState.value]);
const statusDetail = computed(() => {
    const s = store.config?.status;
    if (!s || statusState.value === 'disabled' || statusState.value === 'not_configured') {
        return null;
    }
    const parts = [s.provider];
    if (statusState.value !== 'configured' && s.latency_ms !== null) {
        parts.push(`${s.latency_ms} ms`);
    }
    if (!s.available && s.error) {
        parts.push(s.error);
    }
    return parts.join(' · ');
});

const capabilities = computed<AiProviderCatalogEntry | null>(
    () => catalog.value.find((p) => p.id === form.provider) ?? null,
);
const needsKey = computed(() => capabilities.value?.requires_api_key ?? false);
const hasStoredKey = computed(() => store.config?.has_api_key ?? false);
const storedHint = computed(() => store.config?.api_key_hint ?? null);
const verificationNote = computed(() => {
    const c = store.config;
    if (c?.last_verified_at == null) return null;
    const when = new Date(c.last_verified_at).toLocaleString();
    return c.last_status === 'connected'
        ? `Last verified ${when}.`
        : `Last verification failed ${when}${c.last_error_code ? ` (${c.last_error_code})` : ''}.`;
});

const PROVIDER_OPTIONS = computed(() => [
    { value: 'disabled', label: PROVIDER_LABELS.disabled ?? 'Disabled' },
    ...catalog.value.map((p) => ({ value: p.id, label: PROVIDER_LABELS[p.id] ?? p.id })),
]);

function applyConfig(): void {
    const c = store.config;
    if (c === null) {
        return;
    }
    form.provider = c.provider;
    form.enabled = c.enabled;
    form.model = c.model ?? '';
    form.baseUrl = c.base_url ?? '';
    apiKeyInput.value = '';
    testResult.value = null;
}

onMounted(async () => {
    await Promise.all([store.load(), loadCatalog()]);
    applyConfig();
});

async function loadCatalog(): Promise<void> {
    try {
        const result = await aiApi.providers();
        catalog.value = result.providers;
    } catch {
        catalog.value = [];
    }
}

async function runTest(): Promise<void> {
    formError.value = null;
    testResult.value = null;
    if (needsKey.value && !hasStoredKey.value && apiKeyInput.value.trim() === '') {
        formError.value = 'Provide an API key to test this provider.';
        return;
    }
    testing.value = true;
    try {
        const result = await aiApi.test({
            provider: form.provider,
            base_url: form.baseUrl === '' ? null : form.baseUrl,
            model: form.model === '' ? null : form.model,
            api_key: apiKeyInput.value.trim() === '' ? null : apiKeyInput.value.trim(),
        });
        testResult.value = {
            ok: result.ok,
            text: result.ok
                ? `Connected to ${result.status.provider} — model responded.`
                : `${result.message}${result.code ? ` (${result.code})` : ''}`,
        };
    } catch {
        testResult.value = { ok: false, text: store.error?.message ?? 'Test failed.' };
    } finally {
        testing.value = false;
        void store.load();
    }
}

async function submit(): Promise<void> {
    formError.value = null;
    saved.value = false;
    credentialSaved.value = false;
    if (needsKey.value && !hasStoredKey.value && apiKeyInput.value.trim() === '') {
        formError.value = `${PROVIDER_LABELS[form.provider] ?? form.provider} requires an API key.`;
        return;
    }
    saving.value = true;
    const ok = await store.save({
        provider: form.provider,
        enabled: form.enabled,
        model: form.model === '' ? null : form.model,
        base_url: form.baseUrl === '' ? null : form.baseUrl,
    });
    // A typed key is always rotated through the dedicated endpoint
    // (TASK-P18-022), never smuggled through the settings payload.
    let credentialOk = true;
    if (ok && apiKeyInput.value.trim() !== '') {
        credentialOk = await store.setCredential(apiKeyInput.value.trim());
        credentialSaved.value = credentialOk;
    }
    saving.value = false;
    if (ok && credentialOk) {
        saved.value = true;
        apiKeyInput.value = '';
        applyConfig();
    } else if (!ok) {
        formError.value = store.error?.message ?? 'Could not save settings.';
    }
}

function toggleEnabled(): void {
    void store.setEnabled(!form.enabled).then((ok) => {
        if (ok) {
            applyConfig();
        }
    });
}

async function removeKey(): Promise<void> {
    formError.value = null;
    credentialSaved.value = false;
    const ok = await store.removeCredential();
    if (ok) {
        apiKeyInput.value = '';
        credentialSaved.value = true;
        applyConfig();
    } else {
        formError.value = store.error?.message ?? 'Could not remove the key.';
    }
}
</script>

<template>
    <div class="max-w-lg flex flex-col gap-4" data-testid="ai-settings-view">
        <div v-if="store.loading" class="text-sm text-gray-500 dark:text-gray-400" data-testid="ai-settings-loading">Loading…</div>

        <template v-else>
            <!-- TASK-P25-009 — Settings → AI Usage, summary-first. -->
            <AiUsageSummaryCard />

            <form class="flex flex-col gap-6" @submit.prevent="submit" data-testid="ai-provider-form">
            <div v-if="formError" class="text-sm text-danger" role="alert" data-testid="ai-settings-error">{{ formError }}</div>

            <!-- Section 1 · Runtime Status -->
            <section class="flex flex-col gap-2" data-testid="ai-section-status">
                <h2 class="text-sm font-semibold uppercase tracking-wide">Runtime status</h2>
                <p class="text-sm flex items-baseline gap-2" :class="statusView.tone" data-testid="ai-status-banner">
                    <span class="font-medium uppercase tracking-wide text-xs" data-testid="ai-status-state">{{ statusState.replaceAll('_', ' ') }}</span>
                    <span>{{ statusView.label }}</span>
                    <span v-if="statusDetail" class="text-xs opacity-80">{{ statusDetail }}</span>
                </p>
                <p v-if="verificationNote" class="text-xs text-gray-500 dark:text-gray-400" data-testid="ai-verification-note">{{ verificationNote }}</p>
            </section>

            <!-- Section 2 · Provider -->
            <section class="flex flex-col gap-3" data-testid="ai-section-provider">
                <h2 class="text-sm font-semibold uppercase tracking-wide">Provider</h2>
                <label class="flex flex-col gap-1 text-sm">
                    Provider
                    <select v-model="form.provider" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="ai-provider-select">
                        <option v-for="opt in PROVIDER_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    Model
                    <input v-model="form.model" type="text" placeholder="llama3.1 / gpt-4o-mini" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="ai-model-input" />
                </label>

                <label v-if="capabilities?.supports_remote || capabilities?.supports_local" class="flex flex-col gap-1 text-sm">
                    Base URL
                    <input v-model="form.baseUrl" type="url" placeholder="http://localhost:11434" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="ai-base-url-input" />
                </label>

                <div class="flex items-center gap-2 text-sm">
                    <button
                        type="button"
                        class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-1.5"
                        :data-testid="form.enabled ? 'ai-disable-button' : 'ai-enable-button'"
                        @click.prevent="toggleEnabled"
                    >{{ form.enabled ? 'Disable AI' : 'Enable AI' }}</button>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ form.enabled ? 'AI actions are available.' : 'AI actions are paused.' }}</span>
                </div>
            </section>

            <!-- Section 3 · Credential -->
            <section v-if="form.provider !== 'disabled'" class="flex flex-col gap-3" data-testid="ai-section-credential">
                <h2 class="text-sm font-semibold uppercase tracking-wide">API key</h2>
                <template v-if="needsKey">
                    <SecretField
                        v-model="apiKeyInput"
                        label="API key"
                        :hint="hasStoredKey ? `A key is stored (${storedHint ?? 'masked'}). Enter a new key to replace it.` : null"
                    />
                </template>
                <p v-else class="text-sm text-gray-600 dark:text-gray-400" data-testid="ai-ollama-no-key">
                    This provider does not require an API key.
                </p>
                <p v-if="hasStoredKey" class="text-xs text-gray-500 dark:text-gray-400" data-testid="ai-api-key-hint">
                    Stored key: {{ storedHint ?? 'masked' }}
                </p>
                <div v-if="hasStoredKey" class="flex gap-2">
                    <KButton type="button" variant="ghost" data-testid="ai-remove-key-button" @click.prevent="removeKey">Remove stored key</KButton>
                </div>
                <p v-if="credentialSaved" class="text-xs text-green-700 dark:text-green-400" data-testid="ai-credential-saved">Credential updated.</p>
            </section>

            <!-- Section 4 · Connection -->
            <section class="flex flex-col gap-2" data-testid="ai-section-test">
                <h2 class="text-sm font-semibold uppercase tracking-wide">Connection</h2>
                <KButton type="button" variant="ghost" :disabled="testing" data-testid="ai-test-button" @click.prevent="runTest">
                    {{ testing ? 'Testing…' : 'Test connection' }}
                </KButton>
                <p v-if="testResult" class="text-sm" :class="testResult.ok ? 'text-green-700 dark:text-green-400' : 'text-danger'" data-testid="ai-test-result">
                    {{ testResult.text }}
                </p>
            </section>

            <!-- Section 5 · Privacy -->
            <section class="flex flex-col gap-1 text-xs text-gray-500 dark:text-gray-400" data-testid="ai-section-privacy">
                <h2 class="text-sm font-semibold uppercase tracking-wide">Privacy</h2>
                <p data-testid="ai-privacy-copy">
                    Your API key is encrypted on the Kinevo server and is never sent back to your browser after saving — only a masked hint is shown.
                    Note and task content leaves your machine only when you explicitly run an AI action, and only the minimal context needed for that action.
                </p>
            </section>

            <div class="flex items-center gap-3">
                <KButton type="submit" :disabled="saving" data-testid="ai-save-button">{{ saving ? 'Saving…' : 'Save settings' }}</KButton>
                <span v-if="saved" class="text-sm text-green-700 dark:text-green-400" data-testid="ai-settings-saved">AI provider settings saved.</span>
            </div>
            </form>
        </template>
    </div>
</template>
