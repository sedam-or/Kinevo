import { apiClient } from '../api/client';
import type { BoostSetupResponse, EmergencyPausePayload, EmergencyPauseResponse, EndBreakResponse, EndBoostTargetResponse, MiniPausePayload, MiniPauseResponse, QuickCapturePayload, QuickCaptureResponse, SetBoostTargetPayload, SetBoostTargetResponse, StartBreakPayload, StartBreakResponse, TodayResponse } from './types';
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

    startBreak(payload: StartBreakPayload): Promise<StartBreakResponse> {
        return apiClient.request<StartBreakResponse>('/break', {
            method: 'POST',
            body: JSON.stringify(payload),
            noRetry: true,
        });
    },

    endBreak(): Promise<EndBreakResponse> {
        return apiClient.request<EndBreakResponse>('/break/end', {
            method: 'POST',
            noRetry: true,
        });
    },

    getBoostSetup(): Promise<BoostSetupResponse> {
        return apiClient.request<BoostSetupResponse>('/boost');
    },

    setBoostTarget(payload: SetBoostTargetPayload): Promise<SetBoostTargetResponse> {
        return apiClient.request<SetBoostTargetResponse>('/boost', {
            method: 'POST',
            body: JSON.stringify(payload),
            noRetry: true,
        });
    },

    endBoostTarget(): Promise<EndBoostTargetResponse> {
        return apiClient.request<EndBoostTargetResponse>('/boost/end', {
            method: 'POST',
            noRetry: true,
        });
    },
};
