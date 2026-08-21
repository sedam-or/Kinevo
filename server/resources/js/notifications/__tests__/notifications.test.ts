import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import { notificationApi } from '../api';
import { useNotificationsStore } from '../store';
import NotificationCenter from '../NotificationCenter.vue';
import { groupNotification, type KinevoNotification } from '../types';

vi.mock('../api', () => ({
    notificationApi: {
        list: vi.fn(),
        markRead: vi.fn(),
    },
}));

function todayDate(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function makeNotification(overrides: Partial<KinevoNotification>): KinevoNotification {
    return {
        id: 1,
        user_id: 1,
        type: 'reconciliation',
        scheduled_for: todayDate(),
        title: 'Review schedule changes',
        payload: {},
        read_at: null,
        ...overrides,
    };
}

const unread = makeNotification({});
const today = makeNotification({ id: 2, type: 'break_end', title: 'Break ended', read_at: new Date().toISOString() });
const earlier = makeNotification({ id: 3, scheduled_for: '2020-08-01', title: 'Older note', read_at: new Date().toISOString() });

describe('groupNotification (§29)', () => {
    it('groups unread, today, and earlier', () => {
        expect(groupNotification(unread)).toBe('unread');
        expect(groupNotification(today)).toBe('today');
        expect(groupNotification(earlier)).toBe('earlier');
    });
});

describe('useNotificationsStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('loads notifications and computes the unread count', async () => {
        vi.mocked(notificationApi.list).mockResolvedValue({
            notifications: [unread, today, earlier],
        });
        const store = useNotificationsStore();
        await store.load();
        expect(store.notifications).toHaveLength(3);
        expect(store.unreadCount).toBe(1);
        expect(store.grouped.unread).toHaveLength(1);
    });

    it('marks a notification read', async () => {
        vi.mocked(notificationApi.list).mockResolvedValue({ notifications: [unread] });
        vi.mocked(notificationApi.markRead).mockResolvedValue({
            notification: { ...unread, read_at: new Date().toISOString() },
        });
        const store = useNotificationsStore();
        await store.load();
        expect(store.unreadCount).toBe(1);
        await store.markRead(1);
        expect(store.unreadCount).toBe(0);
    });
});

describe('NotificationCenter', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('renders the bell and opens the center with grouped items', async () => {
        vi.mocked(notificationApi.list).mockResolvedValue({
            notifications: [unread, today, earlier],
        });
        const wrapper = mount(NotificationCenter, { global: { plugins: [createPinia()] } });
        await flushPromises();

        expect(wrapper.find('[data-testid="notifications-bell"]').exists()).toBe(true);
        await wrapper.find('[data-testid="notifications-bell"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-testid="notification-center"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="notification-item"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="notification-title"]').text()).toContain('Review schedule');
    });

    it('marks an unread notification read and updates the count', async () => {
        vi.mocked(notificationApi.list).mockResolvedValue({ notifications: [unread] });
        vi.mocked(notificationApi.markRead).mockResolvedValue({
            notification: { ...unread, read_at: new Date().toISOString() },
        });
        const wrapper = mount(NotificationCenter, { global: { plugins: [createPinia()] } });
        await flushPromises();
        await wrapper.find('[data-testid="notifications-bell"]').trigger('click');
        await flushPromises();

        await wrapper.find('[data-testid="notification-read"]').trigger('click');
        await flushPromises();

        expect(notificationApi.markRead).toHaveBeenCalledWith(1);
        expect(wrapper.find('[data-testid="notifications-count"]').exists()).toBe(false);
    });
});