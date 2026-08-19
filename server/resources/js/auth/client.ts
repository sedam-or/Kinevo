import { apiClient } from '../api/client';
import type { LoginPayload, LoginResponse, MeResponse, ProfileResponse, RegisterPayload, RegisterResponse, UpdateProfilePayload } from './types';

export const authApi = {
    register(payload: RegisterPayload): Promise<RegisterResponse> {
        return apiClient.request<RegisterResponse>('/auth/register', {
            method: 'POST',
            body: JSON.stringify(payload),
            noRetry: true,
        });
    },

    login(payload: LoginPayload): Promise<LoginResponse> {
        return apiClient.request<LoginResponse>('/auth/login', {
            method: 'POST',
            body: JSON.stringify(payload),
            noRetry: true,
        });
    },

    logout(): Promise<{ message: string }> {
        return apiClient.request<{ message: string }>('/auth/logout', { method: 'POST' });
    },

    me(): Promise<MeResponse> {
        return apiClient.request<MeResponse>('/auth/me', { noRetry: true });
    },

    profile(): Promise<ProfileResponse> {
        return apiClient.request<ProfileResponse>('/profile');
    },

    updateProfile(payload: UpdateProfilePayload): Promise<ProfileResponse> {
        return apiClient.request<ProfileResponse>('/profile', {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
    },
};
