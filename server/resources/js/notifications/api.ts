import { apiClient } from '../api/client';
import type { KinevoNotificationListResponse, KinevoNotificationResponse } from './types';

export const notificationApi = {
    list(limit = 200): Promise<KinevoNotificationListResponse> {
        return apiClient.request<KinevoNotificationListResponse>(`/notifications?limit=${limit}`);
    },

    markRead(notificationId: number): Promise<KinevoNotificationResponse> {
        return apiClient.request<KinevoNotificationResponse>(`/notifications/${notificationId}/read`, {
            method: 'POST',
            noRetry: true,
        });
    },
};