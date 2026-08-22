import { describe, expect, it, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useShellStore, SYNC_STATES } from '../store';
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

    it('records and consumes a one-shot deep-open focus per view (TASK-P17-002)', () => {
        const shell = useShellStore();
        expect(shell.consumeFocus('goals')).toBeNull();

        shell.setView('goals', 42);
        expect(shell.activeView).toBe('goals');
        // First consume returns the target and clears it.
        expect(shell.consumeFocus('goals')).toBe(42);
        // Second consume is empty — one-shot semantics.
        expect(shell.consumeFocus('goals')).toBeNull();
    });

    it('navigating without a focus target leaves existing focus untouched', () => {
        const shell = useShellStore();
        shell.setView('tasks', 7);
        shell.setView('canvas');
        expect(shell.activeView).toBe('canvas');
        expect(shell.viewFocus['tasks']).toBe(7);
    });

    it('tracks sync state', () => {
        const shell = useShellStore();
        shell.setSyncState('offline');
        expect(shell.syncState).toBe('offline');
    });

    it('exposes the full visible sync state set (TASK-115)', () => {
        expect(SYNC_STATES).toEqual([
            'online',
            'offline',
            'queued',
            'syncing',
            'saved',
            'conflict',
            'retrying',
            'failed',
        ]);
    });

    it('tracks conflict and retrying sync states', () => {
        const shell = useShellStore();
        shell.setSyncState('conflict');
        expect(shell.syncState).toBe('conflict');
        shell.setSyncState('retrying');
        expect(shell.syncState).toBe('retrying');
    });

    it('tracks the queued mutation count', () => {
        const shell = useShellStore();
        expect(shell.syncQueuedCount).toBe(0);
        shell.setSyncQueuedCount(3);
        expect(shell.syncQueuedCount).toBe(3);
    });

    it('tracks the last sync error and the retry action', () => {
        const shell = useShellStore();
        shell.setSyncError('offline');
        expect(shell.syncError).toBe('offline');
        const retry = () => undefined;
        shell.registerRetrySync(retry);
        expect(shell.retrySync).toBe(retry);
        shell.registerRetrySync(null);
        expect(shell.retrySync).toBeNull();
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
