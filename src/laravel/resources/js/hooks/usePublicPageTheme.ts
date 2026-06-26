import { useTheme } from '@/contexts/ThemeProvider';
import { useEffect } from 'react';

/** Публичные страницы используют системную тему. */
export function usePublicPageTheme() {
    const { setTheme } = useTheme();

    useEffect(() => {
        setTheme('system');
    }, [setTheme]);
}
