import { apiClient } from '../api/client';
import type {
    AdaptiveCheckInPayload,
    AdaptiveObservationListResponse,
    AdaptiveObservationResponse,
    BurnoutSignalResponse,
} from './types';

export const adaptiveApi = {
    list(limit = 20): Promise<AdaptiveObservationListResponse> {
        return apiClient.request<AdaptiveObservationListResponse>(
            `/adaptive/context?limit=${limit}`,
        );
    },

    checkIn(payload: AdaptiveCheckInPayload): Promise<AdaptiveObservationResponse> {
        return apiClient.request<AdaptiveObservationResponse>('/adaptive/context', {
            method: 'POST',
            body: JSON.stringify(payload),
            noRetry: true,
        });
    },

    burnout(): Promise<BurnoutSignalResponse> {
        return apiClient.request<BurnoutSignalResponse>('/adaptive/burnout');
    },
};