import { useTheme } from '@/contexts/ThemeProvider';
import { ThemeMode } from '@/lib/theme';
import {
    ComputerDesktopIcon,
    MoonIcon,
    SunIcon,
} from '@heroicons/react/24/outline';
import React from 'react';
import { twMerge } from 'tailwind-merge';

const THEME_OPTIONS: {
    mode: ThemeMode;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
}[] = [
    { mode: 'light', label: 'Светлая тема', icon: SunIcon },
    { mode: 'dark', label: 'Тёмная тема', icon: MoonIcon },
    { mode: 'system', label: 'Системная тема', icon: ComputerDesktopIcon },
];

export function ThemeSwitcher() {
    const { theme, setTheme } = useTheme();

    return (
        <div
            className="grid grid-flow-col gap-x-1 rounded-lg p-1"
            role="group"
            aria-label="Переключатель темы"
        >
            {THEME_OPTIONS.map(({ mode, label, icon: Icon }) => {
                const isActive = theme === mode;

                return (
                    <button
                        key={mode}
                        type="button"
                        aria-label={label}
                        title={label}
                        onClick={() => setTheme(mode)}
                        className={twMerge(
                            'flex justify-center rounded-md p-2.5 outline-none transition duration-75',
                            isActive
                                ? 'bg-gray-50 text-violet-600 dark:bg-white/5 dark:text-violet-400'
                                : 'text-gray-400 hover:bg-gray-50 hover:text-gray-500 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-400',
                        )}
                    >
                        <Icon className="h-6 w-6" />
                    </button>
                );
            })}
        </div>
    );
}
