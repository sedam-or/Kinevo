import { apiClient } from '../api/client';
import type { AnalyticsOverviewResponse, WorkLifeAnalyticsResponse } from './types';

export const analyticsApi = {
    workLife(from: string, to: string): Promise<WorkLifeAnalyticsResponse> {
        return apiClient.request<WorkLifeAnalyticsResponse>(
            `/analytics/work-life?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
        );
    },

    overview(from: string, to: string): Promise<AnalyticsOverviewResponse> {
        return apiClient.request<AnalyticsOverviewResponse>(
            `/analytics/overview?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
        );
    },
};