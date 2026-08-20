import { apiClient } from '../api/client';
import type { WorkLifeAnalyticsResponse } from './types';

export const analyticsApi = {
    workLife(from: string, to: string): Promise<WorkLifeAnalyticsResponse> {
        return apiClient.request<WorkLifeAnalyticsResponse>(
            `/analytics/work-life?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
        );
    },
};