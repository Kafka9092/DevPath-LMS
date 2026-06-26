export type ThemeMode = 'light' | 'dark' | 'system';

export const THEME_STORAGE_KEY = 'devpath-theme';

export function getStoredTheme(): ThemeMode {
    if (typeof window === 'undefined') {
        return 'system';
    }

    const stored = localStorage.getItem(THEME_STORAGE_KEY);

    if (stored === 'light' || stored === 'dark' || stored === 'system') {
        return stored;
    }

    return 'system';
}

export function getSystemTheme(): 'light' | 'dark' {
    if (typeof window === 'undefined') {
        return 'light';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

export function resolveTheme(mode: ThemeMode): 'light' | 'dark' {
    return mode === 'system' ? getSystemTheme() : mode;
}

export function applyTheme(mode: ThemeMode): 'light' | 'dark' {
    const resolved = resolveTheme(mode);
    const root = document.documentElement;

    root.classList.toggle('dark', resolved === 'dark');
    root.dataset.theme = mode;
    root.style.colorScheme = resolved;

    return resolved;
}

export function persistTheme(mode: ThemeMode): 'light' | 'dark' {
    localStorage.setItem(THEME_STORAGE_KEY, mode);

    return applyTheme(mode);
}
