<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useAiSettingsStore } from './store';
import KButton from '../components/KButton.vue';

const store = useAiSettingsStore();

const PROVIDER_OPTIONS: ReadonlyArray<{ value: string; label: string }> = [
    { value: 'disabled', label: 'Disabled' },
    { value: 'ollama', label: 'Ollama (local)' },
    { value: 'openai', label: 'OpenAI-compatible' },
];

const form = reactive({
    provider: 'disabled',
    enabled: true,
    model: '',
    baseUrl: '',
    apiKey: '',
});
const saved = ref(false);
const saving = ref(false);
const testResult = ref<null | { provider: string; available: boolean; error: string | null }>(null);
const formError = ref<string | null>(null);

const apiKeyRequired = computed(() => form.provider === 'openai');
const ollamaMode = computed(() => form.provider === 'ollama');
const hasStoredKey = computed(() => store.config?.has_api_key ?? false);
const storedHint = computed(() => store.config?.api_key_hint ?? null);

function applyConfig(): void {
    const c = store.config;
    if (c === null) {
        return;
    }
    form.provider = c.provider;
    form.enabled = c.enabled;
    form.model = c.model ?? '';
    form.baseUrl = c.base_url ?? '';
    form.apiKey = '';
    testResult.value = null;
}

onMounted(async () => {
    await store.load();
    applyConfig();
});

async function runTest(): Promise<void> {
    formError.value = null;
    testResult.value = null;
    if (apiKeyRequired.value && form.apiKey.trim() === '' && !hasStoredKey.value) {
        formError.value = 'Provide an API key to test this provider.';
        return;
    }
    const result = await store.test({
        provider: form.provider,
        base_url: form.baseUrl === '' ? null : form.baseUrl,
        model: form.model === '' ? null : form.model,
        api_key: form.apiKey.trim() === '' ? null : form.apiKey.trim(),
    });
    testResult.value = result;
    if (result === null) {
        formError.value = store.error?.message ?? 'Test failed.';
    }
}

async function submit(): Promise<void> {
    formError.value = null;
    saved.value = false;
    if (apiKeyRequired.value && form.apiKey.trim() === '' && !hasStoredKey.value) {
        formError.value = 'OpenAI requires an API key.';
        return;
    }
    saving.value = true;
    const ok = await store.save({
        provider: form.provider,
        enabled: form.enabled,
        model: form.model === '' ? null : form.model,
        base_url: form.baseUrl === '' ? null : form.baseUrl,
        api_key: form.apiKey.trim() === '' ? null : form.apiKey.trim(),
    });
    saving.value = false;
    if (ok) {
        saved.value = true;
        form.apiKey = '';
        applyConfig();
    } else {
        formError.value = store.error?.message ?? 'Could not save settings.';
    }
}
</script>

<template>
    <div class="max-w-lg flex flex-col gap-4" data-testid="ai-settings-view">
        <div v-if="store.loading" class="text-sm text-gray-500 dark:text-gray-400" data-testid="ai-settings-loading">Loading…</div>

        <form v-else class="flex flex-col gap-4" @submit.prevent="submit" data-testid="ai-provider-form">
            <div v-if="formError" class="text-sm text-danger" role="alert" data-testid="ai-settings-error">{{ formError }}</div>
            <div v-if="saved" class="text-sm text-green-700 dark:text-green-400" data-testid="ai-settings-saved">AI provider settings saved.</div>

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

            <label class="flex flex-col gap-1 text-sm">
                Base URL
                <input v-model="form.baseUrl" type="url" placeholder="http://localhost:11434" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="ai-base-url-input" />
            </label>

            <label v-if="!ollamaMode" class="flex flex-col gap-1 text-sm">
                API key
                <input v-model="form.apiKey" type="password" autocomplete="new-password" placeholder="••••••••" class="border border-gray-300 dark:border-gray-600 rounded-sm px-3 py-2" data-testid="ai-api-key-input" />
                <span v-if="hasStoredKey" class="text-xs text-gray-500 dark:text-gray-400" data-testid="ai-api-key-hint">
                    {{ apiKeyRequired ? 'Saved key (' + (storedHint ?? '') + ') — enter a new value to replace.' : 'Key stored.' }}
                </span>
            </label>
            <p v-else class="text-xs text-gray-500 dark:text-gray-400" data-testid="ai-ollama-no-key">Ollama does not require an API key.</p>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.enabled" type="checkbox" class="rounded-sm" data-testid="ai-enabled-toggle" />
                Enable this provider
            </label>

            <div class="flex flex-wrap gap-2">
                <KButton variant="secondary" :disabled="store.testing" data-testid="ai-test-button" @click="runTest">
                    {{ store.testing ? 'Testing…' : 'Test connection' }}
                </KButton>
                <KButton type="submit" variant="primary" :disabled="saving" data-testid="ai-save-button">
                    {{ saving ? 'Saving…' : 'Save' }}
                </KButton>
            </div>

            <p v-if="testResult" class="text-sm" data-testid="ai-test-result">
                <span v-if="testResult.available" class="text-green-700 dark:text-green-400">
                    Connected to {{ testResult.provider }}.
                </span>
                <span v-else class="text-danger">Provider unreachable — {{ testResult.error ?? 'connection failed' }}.</span>
            </p>

            <div class="text-sm text-gray-600 dark:text-gray-400" data-testid="ai-privacy-blurb">
                <p class="font-medium">Privacy</p>
                <p class="text-xs mt-1">
                    Your API key is encrypted at rest and never returned to the app after saving. It is never stored in
                    browser storage. Local Ollama keeps your notes and tasks on this device.
                </p>
            </div>
        </form>
    </div>
</template>