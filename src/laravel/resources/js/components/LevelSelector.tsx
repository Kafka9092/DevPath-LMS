import React from 'react';

interface LevelSelectorProps {
    levels: string[];
    selectedLevel: string | null;
    onLevelSelect: (level: string) => void;
}

export const LevelSelector: React.FC<LevelSelectorProps> = ({ levels, selectedLevel, onLevelSelect }) => {
    const levelLabels: Record<string, string> = {
        beginner: 'Beginner',
        junior: 'Junior',
        middle: 'Middle',
        senior: 'Senior'
    };

    return (
        <div className="space-y-4">
            <p className="text-xs font-bold text-violet-600 dark:text-violet-400 uppercase tracking-wider text-center">
                Уровень сложности
            </p>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                {levels.map((level) => (
                    <button
                        key={level}
                        onClick={() => onLevelSelect(level)}
                        className={`
                            px-6 py-5 rounded-xl border-2 font-semibold text-base
                            transition-all duration-150 active:scale-95 select-none
                            ${selectedLevel === level
                                ? "border-violet-500 bg-violet-50 text-violet-700 shadow-sm dark:border-violet-500 dark:bg-violet-950/40 dark:text-violet-300"
                                : "border-slate-300 bg-white text-slate-600 surface-control hover:border-violet-300 hover:bg-violet-50/40 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-violet-600 dark:hover:bg-violet-950/30"
                            }
                        `}
                    >
                        {levelLabels[level] || level}
                    </button>
                ))}
            </div>
        </div>
    );
};
