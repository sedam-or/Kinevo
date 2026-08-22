import { apiClient } from '../api/client';

export interface AiProviderStatus {
    provider: string;
    model: string;
    available: boolean;
    latency_ms: number | null;
    error: string | null;
}

export interface AiProviderConfigPayload {
    provider: string;
    enabled: boolean;
    model: string | null;
    base_url: string | null;
    has_api_key: boolean;
    api_key_hint: string | null;
    status: AiProviderStatus & { state?: string };
    privacy_ok: boolean;
}

export interface SaveAiProviderConfigInput {
    provider: string;
    enabled?: boolean;
    model?: string | null;
    base_url?: string | null;
    api_key?: string | null;
    remove_api_key?: boolean;
}

export const aiApi = {
    async config(): Promise<{ config: AiProviderConfigPayload }> {
        return apiClient.request<{ config: AiProviderConfigPayload }>('/ai/config');
    },

    async save(input: SaveAiProviderConfigInput): Promise<{ config: AiProviderConfigPayload }> {
        return apiClient.request<{ config: AiProviderConfigPayload }>('/ai/config', {
            method: 'PUT',
            body: JSON.stringify(input),
        });
    },

    async test(input: SaveAiProviderConfigInput): Promise<{ status: AiProviderStatus }> {
        return apiClient.request<{ status: AiProviderStatus }>('/ai/config/test', {
            method: 'POST',
            body: JSON.stringify(input),
        });
    },
};