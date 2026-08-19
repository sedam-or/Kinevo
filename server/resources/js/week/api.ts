import { apiClient } from '../api/client';
import type { CalendarResponse, ScheduleRangeResponse, WeekResponse } from './types';

export const weekApi = {
    week(date: string): Promise<WeekResponse> {
        return apiClient.request<WeekResponse>(`/week?date=${encodeURIComponent(date)}`);
    },

    calendar(month: string): Promise<CalendarResponse> {
        return apiClient.request<CalendarResponse>(`/calendar?month=${encodeURIComponent(month)}`);
    },

    range(from: string, to: string): Promise<ScheduleRangeResponse> {
        return apiClient.request<ScheduleRangeResponse>(
            `/schedule?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
        );
    },
};
