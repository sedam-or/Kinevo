export type ThemePreference = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'kinevo.theme';

export function readThemePreference(): ThemePreference {
    const stored = typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null;
    return stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system';
}

export function writeThemePreference(preference: ThemePreference): void {
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem(STORAGE_KEY, preference);
    }
    applyTheme(preference);
}

export function resolvedTheme(preference: ThemePreference): 'light' | 'dark' {
    if (preference !== 'system') {
        return preference;
    }
    const dark =
        typeof window !== 'undefined' &&
        window.matchMedia &&
        window.matchMedia('(prefers-color-scheme: dark)').matches;
    return dark ? 'dark' : 'light';
}

export function applyTheme(preference: ThemePreference): void {
    if (typeof document === 'undefined') {
        return;
    }
    const resolved = resolvedTheme(preference);
    document.documentElement.classList.toggle('dark', resolved === 'dark');
}
