import {
    applyTheme,
    getStoredTheme,
    persistTheme,
    ThemeMode,
} from '@/lib/theme';
import {
    createContext,
    PropsWithChildren,
    useContext,
    useEffect,
    useState,
} from 'react';

interface ThemeContextValue {
    theme: ThemeMode;
    resolvedTheme: 'light' | 'dark';
    setTheme: (mode: ThemeMode) => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

export function ThemeProvider({ children }: PropsWithChildren) {
    const [theme, setThemeState] = useState<ThemeMode>(() => getStoredTheme());
    const [resolvedTheme, setResolvedTheme] = useState<'light' | 'dark'>(() => {
        if (typeof window === 'undefined') {
            return 'light';
        }

        return applyTheme(getStoredTheme());
    });

    useEffect(() => {
        setResolvedTheme(applyTheme(theme));
    }, [theme]);

    useEffect(() => {
        if (theme !== 'system') {
            return;
        }

        const media = window.matchMedia('(prefers-color-scheme: dark)');

        const handleChange = () => setResolvedTheme(applyTheme('system'));

        media.addEventListener('change', handleChange);

        return () => media.removeEventListener('change', handleChange);
    }, [theme]);

    const setTheme = (mode: ThemeMode) => {
        setResolvedTheme(persistTheme(mode));
        setThemeState(mode);
    };

    return (
        <ThemeContext.Provider value={{ theme, resolvedTheme, setTheme }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme(): ThemeContextValue {
    const context = useContext(ThemeContext);

    if (!context) {
        throw new Error('useTheme must be used within ThemeProvider');
    }

    return context;
}
