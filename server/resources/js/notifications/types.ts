/** In-app notification types (SRS §7, FR-35/FR-47). */

export type KinevoNotificationType = 'reconciliation' | 'break_end';

export interface KinevoNotification {
    id: number;
    user_id: number;
    type: KinevoNotificationType;
    scheduled_for: string | null;
    title: string | null;
    payload: Record<string, unknown>;
    read_at: string | null;
}

export interface KinevoNotificationListResponse {
    notifications: KinevoNotification[];
}

export interface KinevoNotificationResponse {
    notification: KinevoNotification;
}

/** §29 grouping: the bell exposes Unread / Today / Earlier. */
export type KinevoNotificationGroup = 'unread' | 'today' | 'earlier';

export function groupNotification(
    notification: KinevoNotification,
    today: Date = new Date(),
): KinevoNotificationGroup {
    if (notification.read_at === null) {
        return 'unread';
    }
    if (notification.scheduled_for) {
        const [year, month, day] = notification.scheduled_for.split('-').map(Number);
        const scheduled = new Date(year, month - 1, day);
        const now = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        if (scheduled.getTime() >= now.getTime()) {
            return 'today';
        }
    }
    return 'earlier';
}