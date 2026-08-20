import { apiClient } from '../api/client';
import type { RechargeListResponse, RechargeSessionResponse, RechargeStatusResponse } from './types';

export const rechargeApi = {
    status(date?: string): Promise<RechargeStatusResponse> {
        const query = date !== undefined ? `?date=${encodeURIComponent(date)}` : '';
        return apiClient.request<RechargeStatusResponse>(`/recharge/status${query}`);
    },

    list(): Promise<RechargeListResponse> {
        return apiClient.request<RechargeListResponse>('/recharge');
    },

    start(): Promise<RechargeSessionResponse> {
        return apiClient.request<RechargeSessionResponse>('/recharge/start', {
            method: 'POST',
            noRetry: true,
        });
    },

    pause(sessionId: number): Promise<RechargeSessionResponse> {
        return apiClient.request<RechargeSessionResponse>(`/recharge/${sessionId}/pause`, {
            method: 'POST',
            noRetry: true,
        });
    },

    resume(sessionId: number): Promise<RechargeSessionResponse> {
        return apiClient.request<RechargeSessionResponse>(`/recharge/${sessionId}/resume`, {
            method: 'POST',
            noRetry: true,
        });
    },

    complete(sessionId: number): Promise<RechargeSessionResponse> {
        return apiClient.request<RechargeSessionResponse>(`/recharge/${sessionId}/complete`, {
            method: 'POST',
            noRetry: true,
        });
    },

    abandon(sessionId: number): Promise<RechargeSessionResponse> {
        return apiClient.request<RechargeSessionResponse>(`/recharge/${sessionId}/abandon`, {
            method: 'POST',
            noRetry: true,
        });
    },
};