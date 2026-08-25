import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { aiApi, type AiProviderConfigPayload, type SaveAiProviderConfigInput } from './api';
import type { ApiError } from '../api/types';

export const useAiSettingsStore = defineStore('ai-settings', () => {
    const config = ref<AiProviderConfigPayload | null>(null);
    const loading = ref(false);
    const saving = ref(false);
    const testing = ref(false);
    const error = ref<ApiError | null>(null);

    /**
     * TASK-P17-028: one lazy status read shared by every AI-dependent action.
     * Generation is gated only on the canonical server states where AI cannot
     * run at all (disabled / not_configured); unavailable/degraded still let
     * the request through so the server-truth error surfaces (P17-007).
     */
    const generationReady = computed(() => {
        const state = config.value?.status.state;
        return state === 'configured' || state === 'connected';
    });
    let statusPromise: Promise<void> | null = null;

    function ensureStatus(): Promise<void> {
        if (config.value !== null) {
            return Promise.resolve();
        }
        statusPromise ??= load();
        return statusPromise;
    }

    async function load(): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const { config: c } = await aiApi.config();
            config.value = c;
        } catch (err) {
            error.value = err as ApiError;
        } finally {
            loading.value = false;
        }
    }

    async function save(input: SaveAiProviderConfigInput): Promise<boolean> {
        saving.value = true;
        error.value = null;
        try {
            const { config: c } = await aiApi.save(input);
            config.value = c;
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        } finally {
            saving.value = false;
        }
    }

    async function test(input: SaveAiProviderConfigInput): Promise<{ provider: string; available: boolean; error: string | null } | null> {
        testing.value = true;
        error.value = null;
        try {
            const result = await aiApi.test(input);
            // Verification metadata may have advanced server-side.
            void load();
            return { provider: result.status.provider, available: result.status.available, error: result.ok ? null : (result.code ?? result.status.error) };
        } catch (err) {
            error.value = err as ApiError;
            return null;
        } finally {
            testing.value = false;
        }
    }

    /** Credential-only rotation (TASK-P18-022); response is already masked. */
    async function setCredential(apiKey: string): Promise<boolean> {
        saving.value = true;
        error.value = null;
        try {
            const { config: c } = await aiApi.setCredential(apiKey);
            config.value = c;
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        } finally {
            saving.value = false;
        }
    }

    async function removeCredential(): Promise<boolean> {
        saving.value = true;
        error.value = null;
        try {
            const { config: c } = await aiApi.removeCredential();
            config.value = c;
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        } finally {
            saving.value = false;
        }
    }

    async function setEnabled(enabled: boolean): Promise<boolean> {
        error.value = null;
        try {
            const { config: c } = await (enabled ? aiApi.enable() : aiApi.disable());
            config.value = c;
            return true;
        } catch (err) {
            error.value = err as ApiError;
            return false;
        }
    }

    return {
        config, loading, saving, testing, error, generationReady,
        ensureStatus, load, save, test, setCredential, removeCredential, setEnabled,
    };
});