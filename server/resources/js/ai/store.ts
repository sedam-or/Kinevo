import { defineStore } from 'pinia';
import { ref } from 'vue';
import { aiApi, type AiProviderConfigPayload, type SaveAiProviderConfigInput } from './api';
import type { ApiError } from '../api/types';

export const useAiSettingsStore = defineStore('ai-settings', () => {
    const config = ref<AiProviderConfigPayload | null>(null);
    const loading = ref(false);
    const saving = ref(false);
    const testing = ref(false);
    const error = ref<ApiError | null>(null);

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
            const { status } = await aiApi.test(input);
            return { provider: status.provider, available: status.available, error: status.error };
        } catch (err) {
            error.value = err as ApiError;
            return null;
        } finally {
            testing.value = false;
        }
    }

    return { config, loading, saving, testing, error, load, save, test };
});