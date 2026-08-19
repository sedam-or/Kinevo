import { apiClient } from '../api/client';
import type { QuickCapturePayload, QuickCaptureResponse, TodayResponse } from './types';

export const todayApi = {
    today(date: string): Promise<TodayResponse> {
        return apiClient.request<TodayResponse>(`/today?date=${encodeURIComponent(date)}`);
    },

    quickCapture(payload: QuickCapturePayload): Promise<QuickCaptureResponse> {
        return apiClient.request<QuickCaptureResponse>('/quick-capture', {
            method: 'POST',
            body: JSON.stringify(payload),
            noRetry: true,
        });
    },
};
