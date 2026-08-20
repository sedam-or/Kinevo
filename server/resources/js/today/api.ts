import { apiClient } from '../api/client';
import type { EmergencyPausePayload, EmergencyPauseResponse, MiniPausePayload, MiniPauseResponse, QuickCapturePayload, QuickCaptureResponse, TodayResponse } from './types';
import type { ScheduleRangeResponse } from '../week/types';

export const todayApi = {
    today(date: string): Promise<TodayResponse> {
        return apiClient.request<TodayResponse>(`/today?date=${encodeURIComponent(date)}`);
    },

    weekRange(from: string, to: string): Promise<ScheduleRangeResponse> {
        return apiClient.request<ScheduleRangeResponse>(
            `/schedule?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
        );
    },

    quickCapture(payload: QuickCapturePayload): Promise<QuickCaptureResponse> {
        return apiClient.request<QuickCaptureResponse>('/quick-capture', {
            method: 'POST',
            body: JSON.stringify(payload),
            noRetry: true,
        });
    },

    miniPause(payload: MiniPausePayload): Promise<MiniPauseResponse> {
        return apiClient.request<MiniPauseResponse>('/schedule/mini-pause', {
            method: 'POST',
            body: JSON.stringify(payload),
            noRetry: true,
        });
    },

    emergencyPause(payload: EmergencyPausePayload): Promise<EmergencyPauseResponse> {
        return apiClient.request<EmergencyPauseResponse>('/schedule/emergency-pause', {
            method: 'POST',
            body: JSON.stringify(payload),
            noRetry: true,
        });
    },
};
