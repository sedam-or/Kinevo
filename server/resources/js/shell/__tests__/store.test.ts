import { describe, expect, it, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useShellStore } from '../store';
import { PRIMARY_VIEW } from '../navigation';

describe('shell store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('starts at the primary view', () => {
        const shell = useShellStore();
        expect(shell.activeView).toBe(PRIMARY_VIEW);
    });

    it('sets the active view', () => {
        const shell = useShellStore();
        shell.setView('settings');
        expect(shell.activeView).toBe('settings');
    });

    it('tracks sync state', () => {
        const shell = useShellStore();
        shell.setSyncState('offline');
        expect(shell.syncState).toBe('offline');
    });

    it('tracks unread notification count', () => {
        const shell = useShellStore();
        shell.setNotifications([
            { id: 1, title: 'a', unread: true },
            { id: 2, title: 'b', unread: true },
            { id: 3, title: 'c', unread: false },
        ]);
        expect(shell.unreadCount).toBe(2);
    });

    it('tracks global loading state', () => {
        const shell = useShellStore();
        shell.setLoading(true);
        expect(shell.isLoading).toBe(true);
    });

    it('sets and clears the global error message', () => {
        const shell = useShellStore();
        shell.setError('boom');
        expect(shell.errorMessage).toBe('boom');
        shell.setError(null);
        expect(shell.errorMessage).toBeNull();
    });
});
