import { apiClient } from '../api/client';
import type { AnalyticsOverviewResponse, HeatmapAnalyticsResponse, WorkLifeAnalyticsResponse } from './types';

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

    heatmap(from: string, to: string, pillar?: string): Promise<HeatmapAnalyticsResponse> {
        const query = new URLSearchParams({ from, to });
        if (pillar) {
            query.set('pillar', pillar);
        }
        return apiClient.request<HeatmapAnalyticsResponse>(`/analytics/heatmap?${query.toString()}`);
    },
};