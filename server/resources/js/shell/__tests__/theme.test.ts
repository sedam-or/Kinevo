import { afterEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useShellStore } from '../store';
import { applyTheme, readThemePreference, resolvedTheme, writeThemePreference } from '../theme';

const originalMatchMedia = window.matchMedia;
const originalLocalStorage = globalThis.localStorage;

function stubMatchMedia(dark: boolean): void {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: (query: string) => ({
            matches: dark,
            media: query,
            addEventListener: () => {},
            removeEventListener: () => {},
        }),
    });
}

function stubLocalStorage(): void {
    const store = new Map<string, string>();
    Object.defineProperty(globalThis, 'localStorage', {
        writable: true,
        value: {
            getItem: (k: string) => store.get(k) ?? null,
            setItem: (k: string, v: string) => void store.set(k, v),
            removeItem: (k: string) => void store.delete(k),
        },
    });
}

afterEach(() => {
    Object.defineProperty(window, 'matchMedia', { writable: true, value: originalMatchMedia });
    Object.defineProperty(globalThis, 'localStorage', { writable: true, value: originalLocalStorage });
    document.documentElement.classList.remove('dark');
});

describe('shell theme handling', () => {
    it('defaults to system preference', () => {
        stubLocalStorage();
        expect(readThemePreference()).toBe('system');
    });

    it('persists a new preference through the shell store (TASK-P17-013)', () => {
        stubLocalStorage();
        setActivePinia(createPinia());
        const shell = useShellStore();
        shell.setTheme('dark');
        expect(readThemePreference()).toBe('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        shell.setTheme('light');
        expect(readThemePreference()).toBe('light');
        shell.cycleTheme(); // light -> dark
        expect(shell.theme).toBe('dark');
    });

    it('resolves system to dark when the OS is dark', () => {
        stubMatchMedia(true);
        expect(resolvedTheme('system')).toBe('dark');
    });

    it('resolves system to light when the OS is light', () => {
        stubMatchMedia(false);
        expect(resolvedTheme('system')).toBe('light');
    });

    it('resolves explicit preferences without media query', () => {
        stubMatchMedia(false);
        expect(resolvedTheme('dark')).toBe('dark');
        expect(resolvedTheme('light')).toBe('light');
    });

    it('persists and applies a theme', () => {
        stubLocalStorage();
        stubMatchMedia(false);
        writeThemePreference('dark');
        expect(readThemePreference()).toBe('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('applyTheme toggles the dark class only for dark', () => {
        stubMatchMedia(false);
        applyTheme('light');
        expect(document.documentElement.classList.contains('dark')).toBe(false);
        applyTheme('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });
});
